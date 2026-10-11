<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\MessageChannel;
use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\Messaging\MessageSender;
use App\Services\Messaging\VariableRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MessageTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MessageTemplate::query();

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel')->toString());
        }

        $paginator = $query->orderBy('id')->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (MessageTemplate $template) => $this->formatTemplate($template))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request, VariableRenderer $renderer): JsonResponse
    {
        $validated = $request->validate($this->storeRules());

        $variables = $this->resolveVariables(
            $renderer,
            $validated['body'],
            $validated['subject'] ?? null,
            array_key_exists('variables', $validated) ? $validated['variables'] : null,
        );

        $template = MessageTemplate::query()->create([
            'channel' => $validated['channel'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'locale' => $validated['locale'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'variables' => $variables,
            'is_system' => (bool) ($validated['is_system'] ?? false),
        ]);

        return response()->json([
            'data' => $this->formatTemplate($template),
        ], 201);
    }

    public function update(Request $request, MessageTemplate $messageTemplate, VariableRenderer $renderer): JsonResponse
    {
        $validated = $request->validate($this->updateRules($messageTemplate));

        $body = $validated['body'] ?? $messageTemplate->body;
        $subject = array_key_exists('subject', $validated)
            ? $validated['subject']
            : $messageTemplate->subject;

        $variablesInput = array_key_exists('variables', $validated)
            ? $validated['variables']
            : null;

        if ($variablesInput !== null || array_key_exists('body', $validated) || array_key_exists('subject', $validated)) {
            $variables = $this->resolveVariables($renderer, $body, $subject, $variablesInput);
            $messageTemplate->variables = $variables;
        }

        if (array_key_exists('channel', $validated)) {
            $messageTemplate->channel = $validated['channel'];
        }

        if (array_key_exists('code', $validated)) {
            $messageTemplate->code = $validated['code'];
        }

        if (array_key_exists('name', $validated)) {
            $messageTemplate->name = $validated['name'];
        }

        if (array_key_exists('locale', $validated)) {
            $messageTemplate->locale = $validated['locale'];
        }

        if (array_key_exists('subject', $validated)) {
            $messageTemplate->subject = $validated['subject'];
        }

        if (array_key_exists('body', $validated)) {
            $messageTemplate->body = $validated['body'];
        }

        if (array_key_exists('is_system', $validated)) {
            $messageTemplate->is_system = (bool) $validated['is_system'];
        }

        $messageTemplate->save();

        return response()->json([
            'data' => $this->formatTemplate($messageTemplate->fresh()),
        ]);
    }

    public function destroy(MessageTemplate $messageTemplate): JsonResponse
    {
        if ($messageTemplate->is_system) {
            return response()->json([
                'message' => 'System templates cannot be deleted.',
                'code' => 'forbidden',
            ], 403);
        }

        $messageTemplate->delete();

        return response()->json(['ok' => true]);
    }

    public function preview(Request $request, MessageTemplate $messageTemplate, VariableRenderer $renderer): JsonResponse
    {
        $validated = $request->validate([
            'variables' => ['nullable', 'array'],
        ]);

        $variables = is_array($validated['variables'] ?? null) ? $validated['variables'] : [];
        $allowed = is_array($messageTemplate->variables) ? $messageTemplate->variables : [];

        try {
            $subject = $messageTemplate->subject
                ? $renderer->render($messageTemplate->subject, $variables, $allowed)
                : null;
            $body = $renderer->render($messageTemplate->body, $variables, $allowed);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'variables' => [$e->getMessage()],
            ]);
        }

        return response()->json([
            'data' => [
                'subject' => $subject,
                'body' => $body,
            ],
        ]);
    }

    public function sample(Request $request, MessageTemplate $messageTemplate, MessageSender $sender): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'string', 'max:255'],
            'variables' => ['nullable', 'array'],
        ]);

        $variables = is_array($validated['variables'] ?? null) ? $validated['variables'] : [];

        $log = $sender->sendTemplate($messageTemplate, $validated['to'], $variables);

        return response()->json([
            'message_log' => $this->formatLog($log),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTemplate(MessageTemplate $template): array
    {
        return [
            'id' => $template->id,
            'channel' => $template->channel->value,
            'code' => $template->code,
            'name' => $template->name,
            'locale' => $template->locale,
            'subject' => $template->subject,
            'body' => $template->body,
            'variables' => $template->variables,
            'is_system' => $template->is_system,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'channel' => ['required', Rule::enum(MessageChannel::class)],
            'code' => ['required', 'string', 'max:255', 'unique:message_templates,code'],
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:8'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:64'],
            'is_system' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(MessageTemplate $template): array
    {
        return [
            'channel' => ['sometimes', Rule::enum(MessageChannel::class)],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('message_templates', 'code')->ignore($template->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:8'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:64'],
            'is_system' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<int, string>|null  $variablesWhitelist
     * @return array<int, string>
     */
    private function resolveVariables(
        VariableRenderer $renderer,
        string $body,
        ?string $subject,
        ?array $variablesWhitelist,
    ): array {
        $used = array_values(array_unique(array_merge(
            $renderer->extract($body),
            $subject !== null && $subject !== '' ? $renderer->extract($subject) : [],
        )));

        if ($variablesWhitelist === null) {
            return $used;
        }

        $unknown = array_diff($used, $variablesWhitelist);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => ['Unknown template variables: '.implode(', ', $unknown)],
            ]);
        }

        return array_values($variablesWhitelist);
    }
}
