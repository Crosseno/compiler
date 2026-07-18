<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Pipeline;

use Crosseno\Compiler\Exception\InvalidConfiguration;

final readonly class AuxiliaryKeyFactory
{
    /** @param non-empty-list<string> $fields */
    public function clue(string $namespace, array $fields): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,62}$/D', $namespace) !== 1 || $fields === [] || !array_is_list($fields)) {
            throw new InvalidConfiguration('Auxiliary stable-key input is invalid.');
        }
        $bytes = "crosseno-stable-key\0" . pack('C', 1) . $this->frame('clue') . $this->frame($namespace) . pack('N', \count($fields));
        foreach ($fields as $field) {
            if (!\Normalizer::isNormalized($field, \Normalizer::FORM_C)) {
                throw new InvalidConfiguration('Auxiliary stable-key fields must be NFC.');
            }
            $bytes .= $this->frame($field);
        }

        return \sprintf('xk1:clue:%s:%s', $namespace, hash('sha256', $bytes));
    }

    private function frame(string $value): string
    {
        return pack('N', \strlen($value)) . $value;
    }
}
