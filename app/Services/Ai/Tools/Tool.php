<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * One thing the assistant may ask for.
 *
 * Every tool is built with the viewer from the session and never receives
 * a user id from the model. That single constructor argument is most of
 * the security of the feature: no wording of a question can change whose
 * data a tool reads, because the model has no way to supply one.
 *
 * Tools return plain arrays built field by field. Never a model, never
 * toArray() - a column added to a table later must not start appearing in
 * answers just because it exists.
 */
abstract class Tool
{
    /** Rows returned by any one call. Enough to answer with, too few to harvest. */
    protected const LIMIT = 12;

    /** Characters of free text kept per record. */
    protected const EXCERPT = 300;

    public function __construct(protected readonly User $viewer) {}

    abstract public function name(): string;

    /**
     * Kept short on purpose. Every description is re-sent with every
     * request, so each extra sentence is paid for on each round trip
     * against a tokens-per-minute budget.
     */
    abstract public function description(): string;

    /** @return array<string, mixed> a JSON-schema object */
    abstract public function parameters(): array;

    /** @param array<string, mixed> $arguments */
    abstract public function run(array $arguments): array;

    /**
     * Whether this tool is offered to this viewer at all. A tool hidden
     * from the list must still refuse in run() - being unlisted is a
     * courtesy that saves tokens, not the check.
     */
    public function availableTo(User $user): bool
    {
        return true;
    }

    /** @return array{name: string, description: string, parameters: array<string, mixed>} */
    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => $this->parameters(),
        ];
    }

    // ------------------------------------------------------------------
    // Schema helpers. Deliberately plain JSON Schema - no nesting, no
    // unusual keywords - because the vendors accept overlapping but not
    // identical subsets of it.
    // ------------------------------------------------------------------

    protected static function noArguments(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'required' => []];
    }

    /** @param array<string, array<string, mixed>> $properties */
    protected static function object(array $properties, array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required];
    }

    protected static function text(string $description): array
    {
        return ['type' => 'string', 'description' => $description];
    }

    /** @param list<string> $values */
    protected static function choice(array $values, string $description): array
    {
        return ['type' => 'string', 'enum' => $values, 'description' => $description];
    }

    protected function arg(array $arguments, string $key): string
    {
        return trim((string) ($arguments[$key] ?? ''));
    }

    protected function excerpt(?string $text, int $limit = self::EXCERPT): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return \Illuminate\Support\Str::limit(trim(strip_tags($text)), $limit);
    }
}
