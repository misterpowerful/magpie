<?php

declare(strict_types=1);

namespace App\Inbox;

use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Spec section 5 "email" driver: polls a Gmail label (exposed over
 * IMAP as a folder — the label must have "Show in IMAP" enabled in
 * Gmail settings) via an app password, and relabels processed
 * messages from "jobs" to "jobs-processed" so they're never
 * reprocessed (spec: "After processing, relabel jobs to
 * jobs-processed").
 *
 * NOT exercised against a real mailbox by the automated test suite —
 * that needs a real Gmail app password (spec section 11 risk #1,
 * explicitly flagged there as something to "test early" by hand).
 * EmailIntakeService's orchestration logic is tested against a fake
 * MailSource instead.
 */
final class ImapMailSource implements MailSource
{
    public function __construct(
        private readonly ClientManager $manager,
    ) {}

    public function fetchUnprocessed(): array
    {
        $client = $this->connect();
        $folder = $client->getFolderByName(config('magpie.mail.inbox_label'));

        if ($folder === null) {
            throw new \RuntimeException('IMAP folder/label "'.config('magpie.mail.inbox_label').'" not found — check "Show in IMAP" is enabled for this Gmail label.');
        }

        $messages = $folder->messages()->all()->get();

        return $messages->map(fn (Message $message) => $this->toIncomingEmail($message))->all();
    }

    public function markProcessed(IncomingEmail $email): void
    {
        $client = $this->connect();
        $folder = $client->getFolderByName(config('magpie.mail.inbox_label'));
        $message = $folder?->query()->messageId($email->messageId)->get()->first();

        $message?->move(config('magpie.mail.processed_label'));
    }

    public function fetchForConfirmationScan(\DateTimeInterface $since): array
    {
        $client = $this->connect();
        $folder = $client->getFolderByName(config('magpie.mail.confirmation_folder'));

        if ($folder === null) {
            throw new \RuntimeException('IMAP folder "'.config('magpie.mail.confirmation_folder').'" not found.');
        }

        // leaveUnread() sets FT_PEEK so fetching the body never sets the
        // \Seen flag — this scan must never change what the owner sees
        // in his own inbox.
        $messages = $folder->messages()->whereSince($since)->leaveUnread()->get();

        return $messages->map(fn (Message $message) => $this->toIncomingEmail($message))->all();
    }

    private function connect(): Client
    {
        $client = $this->manager->make([
            'host' => config('magpie.mail.host'),
            'port' => config('magpie.mail.port'),
            'encryption' => config('magpie.mail.encryption'),
            'validate_cert' => true,
            'username' => config('magpie.mail.username'),
            'password' => config('magpie.mail.password'),
            'protocol' => 'imap',
        ]);

        $client->connect();

        return $client;
    }

    private function toIncomingEmail(Message $message): IncomingEmail
    {
        $from = $message->getFrom()->get(0);

        return new IncomingEmail(
            messageId: (string) $message->getMessageId(),
            from: $from->mail ?? '',
            subject: (string) $message->getSubject(),
            bodyHtml: $message->getHTMLBody() ?: null,
            bodyText: $message->getTextBody() ?: null,
            receivedAt: $message->getDate()->toDate(),
        );
    }
}
