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

/**
 * Generates phpdoc comments.
 */
class PhpdocGenerator
{
    /**
     * @var int $indentation How many white spaces should precede each phpdoc
     *                       comment line.
     */
    protected int $indentation = 0;
    
    /**
     * @var int $maxLineLength How long should a line go up to before the last
     *                         column has to be wrapped.
     */
    protected int $maxLineLength = 80;
    
    /**
     * @var int $minLastColumnWidth Even if the max line length has to be broken,
     *                              it defines the minimum width of the last
     *                              column. This might happen when the sum of the
     *                              first columns width don't leave a lot of
     *                              spare space for the last column.
     */
    protected int $minLastColumnWidth = 20;
    protected string $bullet = ' * ';

    /**
     * Generates a phpdoc comment from elements provided in an array.
     *
     * Table’s columns will be spaced accordingly. Indentation and word wrap is
     * handle in accordance to the properties ‘indentation’, ‘maxLineLength’ and
     * ‘minLastColumnWidth’. Default indentation is ‘0’, set one with method
     * ‘setIndentation’ before the phpdoc comment is produced if desired.
     *
     * @param array $array Source of the elements that will conform the phpdoc
     *                     comment, this elements must be given in a set of
     *                     tables and rows representing each part of a phpdoc
     *                     comment. For instance, when creating a phpdoc comment
     *                     for a method, we might use one table with a single 
     *                     row and column for its general description. Then, we
     *                     could use another table with multiple rows, one for
     *                     each of the method's arguments, and multiple columns,
     *                     one for each tag and annotation. For instance, 
     *                     column one might contain the tag ‘@param’, and the 
     *                     later ones the annotation of ‘string’, ‘$name’ and 
     *                     ‘Argument description.' respectably. Each row is an 
     *                     array of strings, and each table is an array contain
     *                     them. Each table must follow this convention. The 
     *                     only exception to this would be for a table with both
     *                     a single row with single column, in which case it 
     *                     might be represented by a single string instead of an
     *                     array. Examples of this will be the aforementioned  
     *                     first table for the method's summary or description. 
     *                     Beware that a table with one row but multiple columns
     *                     must still be encapsulated, meaning an array of 
     *                     strings as the single row inside an array as its 
     *                     table. An example of this, and continuing with our 
     *                     hypothetical method phpdoc, would be the tag 
     *                     ‘@return’, which would only required a single row but
     *                     multiple columns. Array keys don't make a difference,
     *                     so you may use them or omit them at will without any
     *                     repercussion.
     *
     * @return string Phpdoc comment created from array given.
     */
    public function fromArray(array $array): string
    {
        $array = array_map(fn($v) => (is_string($v)) ? [[ $v ]]: $v, $array);
        $array = $this->useIntKeysOnly($array);
        $phpdocBlocks = array_map(
            fn($table) => $this->createPhpdocBlock($table),
            $array
        );
        $beginning = $this->indentStr() . "/**" . $this->lineStart();
        $ending = "\n" . $this->indentStr() . ' */';
        $separator = "\n" . $this->indentStr() . " *" . $this->lineStart();
        return $beginning . join($separator, $phpdocBlocks) . $ending;
    }

    protected function useIntKeysOnly($array): array
    {
        return array_map(fn($t) => array_map('array_values', $t), $array);
    }

    protected function createPhpdocBlock(array $table): string
    {
        $columnsWidth = $this->getColumnsWidth($table);
        $lastColumnKey = array_key_last($columnsWidth);
        array_pop($columnsWidth);
        
        $table = $this->formatAllColumnsButLast($table, $columnsWidth);

        $table = $this->formatLastColumn($table, $lastColumnKey, $columnsWidth);

        $phpdocLines = array_map(fn($r) => join('', $r), $table);
        $phpdocLines = array_map('rtrim', $phpdocLines);
        return join($this->lineStart(), $phpdocLines);
    }

    protected function getColumnsWidth(array $table): array
    {
        return array_map(
            fn($column) => max(array_map('strlen', $column)) + 1,
            $this->getColumns($table)
        );
    }

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

    protected function padCells(array $row, array $columnsWidth): array
    {
        return array_map(function(?string $cell, ?int $width) {
            if (is_null($cell) || is_null($width)) {
                return $cell;
            }
            return str_pad($cell, $width);
        }, $row, $columnsWidth);
    }

    protected function getLastColumnWidth(int $widthsSum): int
    {
        $calculation = $this->maxLineLength - $this->indentation -
            strlen($this->bullet) - $widthsSum;
        return ($calculation > $this->minLastColumnWidth) ? $calculation : $this
            ->minLastColumnWidth;
    }

    protected function formatLastColumn(
        array $table, int $key, array $columnsWidth
    ): array {
        $widthsSum = array_sum($columnsWidth);
        $width = $this->getLastColumnWidth($widthsSum);
        $break = $this->lineStart() . str_repeat(' ', $widthsSum);
        return array_map(
            function(array $row) use ($width, $key, $break) {
                if (isset($row[$key]) && !is_null($row[$key])) {
                    $row[$key] = wordwrap($row[$key], $width, $break);
                }
                return $row;
            }
            , $table
        );
    }

    protected function indentStr(): string
    {
        return str_repeat(' ', $this->indentation);
    }

    protected function lineStart(): string
    {
        return "\n" . $this->indentStr() . $this->bullet;
    }

    /**
     * Sets the ‘indentation’ property.
     *
     * Indentation property defines how many white spaces should precede each
     * phpdoc comment line.
     */
    public function setIndentation(int $indentation): self
    {
        $this->indentation = $indentation;
        return $this;
    }

    public function getIndentation(): int
    {
        return $this->indentation;
    }

    public function setMaxLineLength(int $length): self
    {
        $this->maxLineLength = $length;
        return $this;
    }

    public function getMaxLineLength(): int
    {
        return $this->maxLineLength;
    }

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
