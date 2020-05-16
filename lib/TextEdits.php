<?php

namespace Phpactor\TextDocument;

use ArrayIterator;
use Iterator;
use IteratorAggregate;
use OutOfBoundsException;

/**
 * @implements IteratorAggregate<int, TextEdit>
 */
class TextEdits implements IteratorAggregate
{
    /**
     * @var TextEdit[]
     */
    private $textEdits;

    public function __construct(TextEdit ...$textEdits)
    {
        usort($textEdits, function (TextEdit $a, TextEdit $b) {
            return $a->start() <=> $b->start();
        });
        $this->textEdits = $textEdits;
    }

    public static function one(TextEdit $textEdit): self
    {
        return new self($textEdit);
    }

    /**
     * @return Iterator<TextEdit>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->textEdits);
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * @param array<TextEdit> $textEdits
     */
    public static function fromTextEdits(array $textEdits): self
    {
        return new self(...$textEdits);
    }

    /**
     * Merge one set of edits into this set.
     *
     * Edits from this set are ordered before those of the merged edits.
     */
    public function merge(TextEdits $edits): self
    {
        return new self(...array_merge($this->textEdits, $edits->textEdits));
    }

    /**
     * Apply this set of edits to the given text
     */
    public function apply(string $text): string
    {
        $prevEditStart = PHP_INT_MAX;

        for ($i = \count($this->textEdits) - 1; $i >= 0; $i--) {
            $edit = $this->textEdits[$i];
            assert($edit instanceof TextEdit);

            if ($prevEditStart < $edit->start() || $prevEditStart < $edit->start() + $edit->length()) {
                throw new OutOfBoundsException(sprintf(
                    "Overlapping text edit:\n%s",
                    self::renderDebugTextEdits($edit, $this->textEdits)
                ));
            }
            if ($edit->start() < 0) {
                throw new OutOfBoundsException(sprintf(
                    'Start cannot be < 0: %s',
                    self::renderDebugTextEdits($edit, $this->textEdits)
                ));
            }

            if ($edit->length() < 0) {
                throw new OutOfBoundsException(sprintf(
                    'Length cannot be < 0 : %s',
                    self::renderDebugTextEdits($edit, $this->textEdits)
                ));
            }

            if ($edit->end() > \strlen($text)) {
                throw new OutOfBoundsException(sprintf(
                    'Text edit end (%s) exceeds length of text (%s): %s',
                    $edit->end(),
                    $edit->replacement(),
                    self::renderDebugTextEdits($edit, $this->textEdits)
                ));
            }
            $prevEditStart = $edit->start();
            $head = \substr($text, 0, $edit->start());
            $tail = \substr($text, $edit->start() + $edit->length());
            $text = $head . $edit->replacement() . $tail;
        }

        return $text;
    }

    public function add(TextEdit $textEdit): self
    {
        return new self(...array_merge($this->textEdits, [$textEdit]));
    }

    /**
     * @param array<TextEdit> $edits
     */
    private static function renderDebugTextEdits(TextEdit $edit, array $edits): string
    {
        return implode("\n", array_map(function (TextEdit $otherEdit) use ($edit) {
            return sprintf(
                '%s%s %s "%s"',
                $edit === $otherEdit ? '> ' : '  ',
                $otherEdit->start(),
                $otherEdit->start() + $otherEdit->length(),
                str_replace("\n", '\n', $otherEdit->replacement())
            );
        }, $edits));
    }
}
