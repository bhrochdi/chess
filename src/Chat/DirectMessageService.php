<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\Access;
use LeCercle\Shared\Clock;
use LeCercle\Shared\Member;
use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Members;
use LeCercle\Shared\Millis;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\Throttle;
use LeCercle\Shared\ValidationException;

/** Messages privés entre joueurs et compteurs de non-lus. */
final class DirectMessageService
{
    public const THREADS_LIMIT = 12;
    public const CONVERSATION_LIMIT = 100;

    public function __construct(
        private readonly DirectMessageRepository $messages,
        private readonly Access $access,
        private readonly Members $members,
        private readonly Clock $clock,
        private readonly MessageViews $views,
        private readonly Throttle $throttle,
    ) {}

    /** @return array{id: string, pseudo: string} */
    public function open(string $pseudo): array
    {
        $me = $this->access->member();
        $peer = $this->members->findByPseudo(trim($pseudo)) ?? throw new NotFoundException('Joueur introuvable.');
        $this->assertNotSelf($me->id, $peer->id);

        return (new MemberRef($peer->id, $peer->pseudo))->toArray();
    }

    /** @return array{threads: list<array<string, mixed>>, unreadTotal: int} */
    public function threads(): array
    {
        $me = $this->access->member();
        $threads = $this->messages->threads($me->id, self::THREADS_LIMIT);
        $peers = $this->members->findMany(array_map(static fn(ThreadSummary $t): string => $t->peerId, $threads));

        return [
            'threads' => array_map(static fn(ThreadSummary $t): array => [
                'peer' => MemberRef::resolve($t->peerId, $peers)->toArray(),
                'last' => [
                    'text' => $t->last->text,
                    'createdAt' => Millis::of($t->last->createdAt),
                    'fromMe' => $t->last->authorId === $me->id,
                ],
                'unread' => $t->unread,
            ], $threads),
            'unreadTotal' => $this->messages->unreadTotal($me->id),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function conversation(string $peerId): array
    {
        $me = $this->access->member();
        $this->assertNotSelf($me->id, $this->peer($peerId)->id);
        $messages = $this->messages->conversation($me->id, $peerId, self::CONVERSATION_LIMIT);
        if ($messages !== []) {
            $this->messages->markRead($me->id, $peerId, $messages[\count($messages) - 1]->id);
        }

        return $this->views->list($messages);
    }

    /**
     * Relecture périodique : $known est l'identifiant du dernier message que le navigateur affiche
     * (0 pour une conversation vide). Répond null si c'est toujours le dernier : il a déjà été marqué lu.
     *
     * @return list<array<string, mixed>>|null
     */
    public function conversationIfNew(string $peerId, int $known): ?array
    {
        $me = $this->access->member();
        $this->assertNotSelf($me->id, $this->peer($peerId)->id);
        if ($this->messages->lastId($me->id, $peerId) === $known) {
            return null;
        }

        return $this->conversation($peerId);
    }

    /** @return array<string, mixed> */
    public function send(string $peerId, string $text): array
    {
        $me = $this->access->member();
        $peer = $this->peer($peerId);
        $this->assertNotSelf($me->id, $peer->id);
        $text = MessageText::clean($text);
        $now = $this->clock->now();
        MessageRate::check($this->throttle, $me->id, $now);
        $message = $this->messages->add($me->id, $peer->id, $text, $now);

        return $this->views->one($message);
    }

    private function peer(string $peerId): Member
    {
        return $this->members->find($peerId) ?? throw new NotFoundException('Joueur introuvable.');
    }

    private function assertNotSelf(string $meId, string $peerId): void
    {
        if ($meId === $peerId) {
            throw new ValidationException('Tu ne peux pas t’écrire à toi-même.');
        }
    }
}
