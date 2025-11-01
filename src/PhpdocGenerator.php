<?php

/**
 * Part of the arielenter/array-to-phpdoc package.
 *
 * PHP version 8+
 *
 * @category  Phpdoc
 * @package   Arielenter\ArrayToPhpdoc
 * @author    Ariel Del Valle Lozano <arielmazatlan@gmail.com>
 * @copyright 2025 Ariel Del Valle Lozano
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public
 *            License (GPL) version 3
 * @link      https://github.com/arielenter/array-to-phpdoc
 */

namespace Arielenter\ArrayToPhpdoc;

/** Generates phpdoc comments as strings. */
class PhpdocGenerator
{
    /**
     * @var int $indentWidth If it’s value is more than 0, an indentation will
     *                       be given to the resulting phpdoc comment. If
     *                       property ‘useTabForIndentation’ is set to true, a
     *                       single tab will be used and this value will be used
     *                       as a reference of its size (in spaces) to construct
     *                       the phpdoc comment, otherwise a set of spaces will
     *                       be used as indentation using this property as the
     *                       amount of spaces to use.
     */
    protected int $indentWidth = 0;

    /**
     * @var bool $useTabForIndentation Establishes weather or not a tab should
     *                                 be used for indentation instead of
     *                                 spaces whenever property ‘indentWidth’ is
     *                                 greater than 0.
     */
    protected bool $useTabForIndentation = false;

    /**
     * @var int $maxLineLength How long (number of characters) a line can go up
     *                         to before is wrapped.
     */
    protected int $maxLineLength = 80;

    /**
     * @var int $minLastColumnWidth Even if the max line length has to be
     *                              broken, it defines the minimum width (number
     *                              of characters) of the last column of text.
     *                              This might happen when the sum of the first
     *                              columns widths don't leave a lot of spare
     *                              space for the last column in a table.
     */
    protected int $minLastColumnWidth = 20;
    protected string $bullet = ' * ';

    /**
     * Generates a phpdoc comment as a string from the values of an array.
     *
     * It handles the phpdoc’s comment text formatting: The opener, closer and
     * starting asterisk in between lines (only if a multi-line phpdoc is
     * needed), line wrap, indentation and plain text table creations.
     *
     * @param array $array Array containing the values that will be used to
     *                     create a phpdoc comment. Supported values are:
     *                     Strings, which could be used for summaries and
     *                     descriptions; Arrays containing multiple arrays of
     *                     strings, which can be used to create tables like
     *                     argument lists or group document tags that go along
     *                     together; And finally arrays of strings, which may be
     *                     used for single row tables like the ‘@return’ tag.
     *                     Array keys don't make a difference, so you may use
     *                     them or omit them at will without any repercussion.
     *
     * @return string Phpdoc comment created from the array given.
     */
    public function fromArray(array $array): string
    {
        $array = $this->convertStringsToOneRowOneColumnTables($array);
        $array = $this->convertArraysOfStringsToOneRowMultiClmnsTables($array);
        $phpdocBlocks = array_map(
            fn($table) => $this->createPhpdocBlock($table),
            $array
        );
        return $this->createPhpdocFromBlocks($phpdocBlocks);
    }

    /**
     * @param array $array
     */
    protected function convertStringsToOneRowOneColumnTables(
        array $array
    ): array {
        return array_map(fn($t) => (is_string($t)) ? [[ $t ]] : $t, $array);
    }

    /**
     * @param array $array
     */
    protected function convertArraysOfStringsToOneRowMultiClmnsTables(
        array $array
    ): array {
        return array_map(
            function($table) {
                $table = array_values($table);
                if(is_string($table[0])) {
                    $table = [ $table ];
                }
                return $table;
            },
            $array
        );
    }

    /**
     * @param array $table
     */
    protected function createPhpdocBlock(array $table): string
    {
        $table = array_map('array_values', $table);
        $columnsWidth = $this->getColumnsWidth($table);
        $lastColumnKey = array_key_last($columnsWidth);
        array_pop($columnsWidth);

        $table = $this->formatAllColumnsButLast($table, $columnsWidth);

        $table = $this->formatLastColumn(
            $table, $lastColumnKey, array_sum($columnsWidth)
        );

        $phpdocLines = array_map(fn($row) => join('', $row), $table);
        $phpdocLines = array_map('rtrim', $phpdocLines);
        return join($this->lineStart(), $phpdocLines);
    }

    /**
     * @param array $table
     *
     * @return array
     */
    protected function getColumnsWidth(array $table): array
    {
        return array_map(
            fn($column) => max(array_map('strlen', $column)) + 1, // +1 space
            $this->getColumns($table)
        );
    }

    /**
     * @param array $table
     */
    protected function getColumns(array $table): array
    {
        $columns = [];
        foreach ($table as $row) {
            foreach ($row as $key => $cell) {
                $columns[$key][] = $cell;
            }
        }
        return $columns;
    }

    /**
     * @param array          $table
     * @param array<int,int> $columnsWidth
     *
     * @return array
     */
    protected function formatAllColumnsButLast(
        array $table, array $columnsWidth
    ): array {
        if (count($columnsWidth) == 0) {
            return $table;
        }
        return array_map(
            fn($row) => $this->padCells($row, $columnsWidth),
            $table
        );
    }

    /**
     * @param array          $row
     * @param array<int,int> $columnsWidth
     */
    protected function padCells(array $row, array $columnsWidth): array
    {
        return array_map(
            function(?string $cell, ?int $width) {
                if (is_null($cell) || is_null($width)) {
                    return $cell;
                }
                return str_pad($cell, $width);
            },
            $row, $columnsWidth
        );
    }

    /**
     * @param array $table
     */
    protected function formatLastColumn(
        array $table, int $key, int $widthsSum
    ): array {
        $width = $this->getLastColumnWidth($widthsSum);
        $break = $this->lineStart() . str_repeat(' ', $widthsSum);
        return array_map(
            function(array $row) use ($width, $key, $break) {
                if (isset($row[$key]) && !is_null($row[$key])) {
                    $row[$key] = wordwrap($row[$key], $width, $break);
                }
                return $row;
            },
            $table
        );
    }

    protected function getLastColumnWidth(int $widthsSum): int
    {
        $calculation = $this->maxLineLength - $this->indentWidth -
            strlen($this->bullet) - $widthsSum;
        return ($calculation > $this->minLastColumnWidth) ? $calculation : $this
            ->minLastColumnWidth;
    }

    protected function indentStr(): string
    {
        if ($this->useTabForIndentation == true && $this->indentWidth > 0) {
            return "\t";
        }
        return str_repeat(' ', $this->indentWidth);
    }

    protected function lineStart(): string
    {
        return "\n" . $this->indentStr() . $this->bullet;
    }

    /**
     * @param array $blocks
     */
    protected function createPhpdocFromBlocks(array $blocks): string
    {
        $opener = "/**";
        $closer = ' */';
        if (count($blocks) == 1 && !str_contains("\n", $blocks[0])) {
            $oneLiner = $opener . ' ' . $blocks[0] . $closer;
            $length = $this->indentWidth + strlen($oneLiner);
            if ($length <= $this->maxLineLength) {
                return $this->indentStr() . $oneLiner;
            }
        }
        $opening = $this->indentStr() . $opener . $this->lineStart();
        $closing = "\n" . $this->indentStr() . $closer;
        $separator = "\n" . $this->indentStr() . " *" . $this->lineStart();
        return $opening . join($separator, $blocks) . $closing;
    }

    /**
     * Sets the value of the 'indentWidth' property.
     *
     * If a value greater than 0 is stablished, an indentation will be given to
     * the resulting phpdoc comment. If property ‘useTabForIndentation’ is set
     * to true, a single tab will be used and this value will be used as a
     * reference of its size (in spaces) to construct the phpdoc comment,
     * otherwise a set of spaces will be used as indentation using this property
     * as the amount of spaces to use.
     */
    public function setIndentWidth(int $width): self
    {
        $this->indentWidth = $width;
        return $this;
    }

    public function getIndentWidth(): int
    {
        return $this->indentWidth;
    }

    /**
     * Sets the ‘useTabForIndentation’ property.
     *
     * Establishes weather or not a tab would be used for indentation instead of
     * spaces, whenever property ‘indentWidth’ is greater than 0.
     */
    public function setUseTabForIndentation(bool $bool): self
    {
        $this->useTabForIndentation = $bool;
        return $this;
    }

    public function getUseTabForIndentation(): bool
    {
        return $this->useTabForIndentation;
    }

    /**
     * Sets the ‘maxLineLength’ property,
     *
     * Stablishes how long (number of characters) a line can go up to before is
     * wrapped.
     */
    public function setMaxLineLength(int $length): self
    {
        $this->maxLineLength = $length;
        return $this;
    }

    public function getMaxLineLength(): int
    {
        return $this->maxLineLength;
    }

    /**
     * Sets the ‘minLastColumnWidth’ property.
     *
     * Even if the max line length has to be broken, it defines the minimum
     * width (number of characters) of the last column of text. This might
     * happen when the sum of the first columns widths don't leave a lot of
     * spare space for the last column in a table.
     */
    public function setMinLastColumnWidth(int $length): self
    {
        $this->minLastColumnWidth = $length;
        return $this;
    }

    public function getMinLastColumnWidth(): int
    {
        return $this->minLastColumnWidth;
    }
}
