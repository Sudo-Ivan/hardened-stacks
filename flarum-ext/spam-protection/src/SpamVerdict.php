<?php

namespace HardenedStacks\SpamProtection;

final class SpamVerdict
{
    /**
     * @param list<string> $actions
     */
    public function __construct(
        public readonly bool $isSpam,
        public readonly int $confidence,
        public readonly array $actions,
        public readonly string $reason
    ) {
    }

    public static function clean(): self
    {
        return new self(false, 0, [], '');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $actions = [];
        if (isset($data['actions']) && is_array($data['actions'])) {
            foreach ($data['actions'] as $action) {
                if (is_string($action) && $action !== '') {
                    $actions[] = $action;
                }
            }
        }

        $confidence = 0;
        if (isset($data['confidence']) && is_numeric($data['confidence'])) {
            $confidence = (int) round(((float) $data['confidence']) <= 1
                ? ((float) $data['confidence']) * 100
                : (float) $data['confidence']);
        }

        return new self(
            ! empty($data['is_spam']),
            max(0, min(100, $confidence)),
            array_values(array_unique($actions)),
            is_string($data['reason'] ?? null) ? (string) $data['reason'] : ''
        );
    }
}
