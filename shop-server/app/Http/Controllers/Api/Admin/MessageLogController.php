<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\MessageChannel;
use App\Enums\MessageSendStatus;
use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['sometimes', Rule::enum(MessageChannel::class)],
            'status' => ['sometimes', Rule::enum(MessageSendStatus::class)],
            'campaign_id' => ['sometimes', 'integer', 'exists:message_campaigns,id'],
            'to_address' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ]);

        $paginator = $this->filteredQuery($validated)->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MessageLog $log) => $this->formatLog($log))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'channel' => ['sometimes', Rule::enum(MessageChannel::class)],
            'status' => ['sometimes', Rule::enum(MessageSendStatus::class)],
            'campaign_id' => ['sometimes', 'integer', 'exists:message_campaigns,id'],
            'to_address' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ]);

        $filename = 'message-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($validated): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'channel',
                'template_id',
                'campaign_id',
                'to_address',
                'subject',
                'body_rendered',
                'status',
                'provider',
                'provider_ref',
                'error',
                'created_at',
            ]);

            $this->filteredQuery($validated)
                ->orderBy('id')
                ->chunkById(200, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            $log->channel->value,
                            $log->template_id,
                            $log->campaign_id,
                            $log->to_address,
                            $log->subject,
                            $log->body_rendered,
                            $log->status->value,
                            $log->provider,
                            $log->provider_ref,
                            $log->error,
                            $log->created_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = MessageLog::query()->orderByDesc('id');

        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (isset($filters['to_address'])) {
            $query->where('to_address', 'ilike', '%'.$filters['to_address'].'%');
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLog(MessageLog $log): array
    {
        return [
            'id' => $log->id,
            'channel' => $log->channel->value,
            'template_id' => $log->template_id,
            'campaign_id' => $log->campaign_id,
            'direction' => $log->direction,
            'to_address' => $log->to_address,
            'subject' => $log->subject,
            'body_rendered' => $log->body_rendered,
            'status' => $log->status->value,
            'provider' => $log->provider,
            'provider_ref' => $log->provider_ref,
            'error' => $log->error,
            'meta' => $log->meta,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
