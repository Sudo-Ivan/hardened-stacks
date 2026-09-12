<?php

namespace HardenedStacks\Lxmf\Listener;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\Translator;
use Flarum\User\Event\Saving;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Illuminate\Support\Arr;
use HardenedStacks\Lxmf\LxmfAddress;

class SaveLxmfAddress
{
    public function __construct(
        private Translator $translator
    ) {
    }

    public function handle(Saving $event): void
    {
        $attributes = Arr::get($event->data, 'attributes', []);
        if (! array_key_exists('lxmfAddress', $attributes)) {
            return;
        }

        $actor = $event->actor;
        $user = $event->user;

        if ($actor->id !== $user->id && ! $actor->can('edit', $user)) {
            throw new PermissionDeniedException();
        }

        $raw = $attributes['lxmfAddress'];
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            $user->lxmf_address = null;

            return;
        }

        $normalized = LxmfAddress::normalize((string) $raw);
        if (! LxmfAddress::isValid($normalized)) {
            throw new ValidationException([
                'lxmfAddress' => [$this->translator->trans('hardened-stacks-lxmf.validation.invalid')],
            ]);
        }

        $taken = User::query()
            ->where('lxmf_address', $normalized)
            ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists();

        if ($taken) {
            throw new ValidationException([
                'lxmfAddress' => [$this->translator->trans('hardened-stacks-lxmf.validation.taken')],
            ]);
        }

        $user->lxmf_address = $normalized;
    }
}
