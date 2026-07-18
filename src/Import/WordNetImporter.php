<?php

declare(strict_types=1);

namespace Crosseno\Compiler\Import;

use Crosseno\Compiler\Exception\InvalidSourceRecord;

/** Imports [{id,pos,definition,lemmas:[...]}] WordNet-like JSON. */
final readonly class WordNetImporter extends AbstractFileImporter
{
    public function __construct(private RecordMapper $mapper = new RecordMapper()) {}

    public function import(SourceInput $source, ImportLimits $limits): iterable
    {
        $this->assertSize($source, $limits);
        $json = file_get_contents($source->path);
        try {
            $synsets = \is_string($json) ? json_decode($json, true, max(1, $limits->maximumJsonDepth), JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException $exception) {
            throw new InvalidSourceRecord('WordNet source is invalid JSON.', previous: $exception);
        }
        if (!\is_array($synsets) || !array_is_list($synsets)) {
            throw new InvalidSourceRecord('WordNet source root must be a list.');
        }
        $number = 0;
        foreach ($synsets as $synset) {
            if (!\is_array($synset) || array_is_list($synset) || !isset($synset['lemmas']) || !\is_array($synset['lemmas']) || !array_is_list($synset['lemmas'])) {
                throw new InvalidSourceRecord('WordNet synset must be an object with a lemma list.');
            }
            foreach ($synset['lemmas'] as $lemma) {
                ++$number;
                $this->assertRecord($number, $limits);
                if (!\is_string($lemma)) {
                    throw new InvalidSourceRecord('WordNet lemma must be a string.');
                }
                yield $this->mapper->map([
                    'answer' => str_replace('_', ' ', $lemma),
                    'lemma' => str_replace('_', ' ', $lemma),
                    'language' => $synset['language'] ?? 'en',
                    'part_of_speech' => $synset['pos'] ?? null,
                    'sense_id' => $synset['id'] ?? null,
                    'definition' => $synset['definition'] ?? null,
                    'frequency' => $synset['frequency'] ?? 0,
                    'answer_classes' => $synset['answer_classes'] ?? ['standard'],
                    'themes' => $synset['topics'] ?? [],
                ], $source->sourceId, $number, $limits);
            }
        }
    }
}
