<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\CampaignStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageSendStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMessageCampaignJob;
use App\Models\MessageCampaign;
use App\Models\MessageCampaignRecipient;
use App\Models\MessageTemplate;
use App\Services\Messaging\SpreadsheetAudienceParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MessageCampaignController extends Controller
{
    public function __construct(private SpreadsheetAudienceParser $audienceParser) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = MessageCampaign::query()
            ->with('template:id,code,name,channel')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MessageCampaign $campaign) => $this->formatCampaign($campaign))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(MessageCampaign $messageCampaign): JsonResponse
    {
        $messageCampaign->load('template:id,code,name,channel,subject,body');

        return response()->json([
            'data' => $this->formatCampaign($messageCampaign, includeTemplateBody: true),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:message_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::enum(MessageChannel::class)],
        ]);

        $template = MessageTemplate::query()->findOrFail($validated['template_id']);

        if ($template->channel->value !== $validated['channel']) {
            throw ValidationException::withMessages([
                'channel' => ['Channel must match the selected template.'],
            ]);
        }

        $campaign = MessageCampaign::query()->create([
            'template_id' => $template->id,
            'name' => $validated['name'],
            'channel' => $validated['channel'],
            'status' => CampaignStatus::Draft,
            'audience_type' => 'manual_excel',
            'created_by' => $request->user()->id,
            'stats' => [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
            ],
        ]);

        $campaign->load('template:id,code,name,channel');

        return response()->json([
            'data' => $this->formatCampaign($campaign),
        ], 201);
    }

    public function upload(Request $request, MessageCampaign $messageCampaign): JsonResponse
    {
        if ($messageCampaign->status === CampaignStatus::Cancelled) {
            return response()->json([
                'message' => 'Cancelled campaigns cannot accept uploads.',
                'code' => 'forbidden',
            ], 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $validated['file'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            throw ValidationException::withMessages([
                'file' => ['Only .csv and .xlsx uploads are supported.'],
            ]);
        }

        try {
            $rows = $this->audienceParser->parse($file);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'file' => [$e->getMessage()],
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['The upload contains no recipient rows.'],
            ]);
        }

        $addressColumn = $messageCampaign->channel === MessageChannel::Email ? 'email' : 'phone';

        if (! array_key_exists($addressColumn, $rows[0])) {
            throw ValidationException::withMessages([
                'file' => ["Spreadsheet must include an \"{$addressColumn}\" column for this channel."],
            ]);
        }

        $created = 0;

        DB::transaction(function () use ($messageCampaign, $rows, &$created): void {
            MessageCampaignRecipient::query()
                ->where('campaign_id', $messageCampaign->id)
                ->delete();

            foreach ($rows as $row) {
                $variables = $row;
                unset($variables['email'], $variables['phone']);

                MessageCampaignRecipient::query()->create([
                    'campaign_id' => $messageCampaign->id,
                    'email' => $messageCampaign->channel === MessageChannel::Email
                        ? ($row['email'] ?? null)
                        : null,
                    'phone' => $messageCampaign->channel === MessageChannel::Sms
                        ? ($row['phone'] ?? null)
                        : null,
                    'variables' => $variables !== [] ? $variables : null,
                    'status' => MessageSendStatus::Pending,
                ]);

                $created++;
            }

            $stats = is_array($messageCampaign->stats) ? $messageCampaign->stats : [];
            $stats['total'] = $created;
            $stats['sent'] = (int) ($stats['sent'] ?? 0);
            $stats['failed'] = (int) ($stats['failed'] ?? 0);
            $stats['skipped'] = (int) ($stats['skipped'] ?? 0);

            $messageCampaign->stats = $stats;
            $messageCampaign->save();
        });

        return response()->json([
            'data' => $this->formatCampaign($messageCampaign->fresh()->load('template:id,code,name,channel')),
            'recipients_imported' => $created,
        ]);
    }

    public function schedule(Request $request, MessageCampaign $messageCampaign): JsonResponse
    {
        if ($messageCampaign->status === CampaignStatus::Cancelled) {
            return response()->json([
                'message' => 'Cancelled campaigns cannot be scheduled.',
                'code' => 'forbidden',
            ], 403);
        }

        if ((int) ($messageCampaign->stats['total'] ?? 0) === 0) {
            throw ValidationException::withMessages([
                'campaign' => ['Upload recipients before scheduling this campaign.'],
            ]);
        }

        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $scheduledAt = isset($validated['scheduled_at'])
            ? Carbon::parse($validated['scheduled_at'])
            : null;

        $messageCampaign->scheduled_at = $scheduledAt;

        if ($scheduledAt !== null && $scheduledAt->isFuture()) {
            $messageCampaign->status = CampaignStatus::Scheduled;
            $messageCampaign->save();

            ProcessMessageCampaignJob::dispatch($messageCampaign->id)->delay($scheduledAt);
        } else {
            $messageCampaign->status = CampaignStatus::Processing;
            $messageCampaign->save();

            ProcessMessageCampaignJob::dispatch($messageCampaign->id);
        }

        return response()->json([
            'data' => $this->formatCampaign($messageCampaign->fresh()->load('template:id,code,name,channel')),
        ]);
    }

    public function cancel(MessageCampaign $messageCampaign): JsonResponse
    {
        if ($messageCampaign->status === CampaignStatus::Cancelled) {
            return response()->json([
                'data' => $this->formatCampaign($messageCampaign->load('template:id,code,name,channel')),
            ]);
        }

        $messageCampaign->status = CampaignStatus::Cancelled;
        $messageCampaign->save();

        return response()->json([
            'data' => $this->formatCampaign($messageCampaign->fresh()->load('template:id,code,name,channel')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCampaign(MessageCampaign $campaign, bool $includeTemplateBody = false): array
    {
        $template = $campaign->relationLoaded('template') && $campaign->template !== null
            ? [
                'id' => $campaign->template->id,
                'code' => $campaign->template->code,
                'name' => $campaign->template->name,
                'channel' => $campaign->template->channel->value,
            ]
            : null;

        if ($includeTemplateBody && $campaign->relationLoaded('template') && $campaign->template !== null) {
            $template['subject'] = $campaign->template->subject;
            $template['body'] = $campaign->template->body;
        }

        return [
            'id' => $campaign->id,
            'channel' => $campaign->channel->value,
            'template_id' => $campaign->template_id,
            'template' => $template,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'audience_type' => $campaign->audience_type,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'created_by' => $campaign->created_by,
            'stats' => $campaign->stats,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
        ];
    }
}
