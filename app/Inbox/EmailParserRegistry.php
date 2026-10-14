<?php

namespace App\Inbox;

/**
 * Tries each registered parser in order and uses the first one that
 * claims the email. HaikuFallbackParser always claims everything, so
 * it must be registered last.
 */
class EmailParserRegistry
{
    /** @var array<int, EmailParser> */
    private array $parsers = [];

    public function register(EmailParser $parser): static
    {
        $this->parsers[] = $parser;

        return $this;
    }

    public function resolve(IncomingEmail $email): ?EmailParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canParse($email)) {
                return $parser;
            }
        }

        return null;
    }
}
