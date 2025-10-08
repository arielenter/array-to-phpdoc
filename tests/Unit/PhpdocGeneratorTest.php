<?php

namespace Tests\Unit;

use Arielenter\ArrayToPhpdoc\PhpdocGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhpdocGeneratorTest extends TestCase
{
    #[Test]
    public function creates_phpdoc_from_an_array(): void
    {
        [ $array, $expected ] = $this->exampleOne();
        
        $actual = (new PhpdocGenerator)->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    public function exampleOne(): array
    {
        $array = [
            [[ 'Example document description.' ]],
            [
                [ '@author', 'Example Author Name' ],
                [ '@copyright', '2025 Example Author Name' ]
            ]
        ];
        $expected = "/**\n * " . $array[0][0][0] . "\n *\n * "
            . join('    ', $array[1][0]) . "\n * " . join(' ', $array[1][1])
            . "\n */";

        return [ $array, $expected];
    }

    #[Test]
    public function single_row_and_column_block_can_be_a_string(): void
    {
        [ $array, $expected ] = $this->exampleOne();

        $array[0] = $array[0][0][0];
        
        $actual = (new PhpdocGenerator)->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function string_keys_do_not_matter(): void
    {
        [ $array, $expected ] = $this->exampleOne();

        $arrayWithStringKeys = [
            'a' => [ 'b' => [ 'c' => $array[0][0][0] ]],
            'd' => [
                'e' => [ 'f' => $array[1][0][0], 'g' => $array[1][0][1] ],
                'h' => [ 'i' => $array[1][1][0], 'j' => $array[1][1][1] ]
            ]
        ];

        $actual = (new PhpdocGenerator)->fromArray($arrayWithStringKeys);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function an_indentation_can_be_set(): void
    {
        $indentation = 6;
        
        [ $array, $expected, $generator ] = $this->exampleTwo($indentation);

        $this->assertEquals($indentation, $generator->getIndentation());

        $actual = $generator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    public function exampleTwo(int $indentation = 4): array
    {
        $array = [
            'Example method description.',
            [
                [ '@param', 'string', '$name',    'Name param description.' ],
                [ '@param', 'array',  '$longger', 'Longger param description' ]
            ],
            [[ '@return', 'string', 'Return value description.' ]]
        ];

        $expected = "/**\n * " . $array[0] . "\n *\n * " . $array[1][0][0] . ' '
            . $array[1][0][1] . ' ' . $array[1][0][2] . '    '
            . $array[1][0][3] . "\n * " . $array[1][1][0] . ' '
            . $array[1][1][1] . '  ' . $array[1][1][2] . ' ' . $array[1][1][3]
        . "\n *\n * " . join(' ', $array[2][0]) . "\n */";

        $generator = (new PhpdocGenerator)->setIndentation($indentation);

        $expected = $this->indentContent($indentation, $expected);

        return [ $array, $expected, $generator ];
    }

    public function indentContent(int $indentation, string $expected): string
    {
        $indentStr = str_repeat(' ', $indentation);
        return $indentStr . str_replace("\n", "\n$indentStr", $expected);
    }

    #[Test]
    public function last_column_of_a_row_can_be_omitted(): void
    {
        [ $array, $expected, $generator ] = $this->exampleTwo();

        $expected = str_replace(' ' . $array[1][1][3], '', $expected);
        unset($array[1][1][3]);
        $actual = $generator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function last_column_is_wrap(): void
    {
        [ $array, $expected, $generator ] = $this->exampleThree(4);

        $actual = $generator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    public function exampleThree(int $indent, ?int $maxLineLength = null): array
    {
        $descs = [];
        $generator = (new PhpdocGenerator)->setIndentation($indent);
        (!is_null($maxLineLength)) && $generator
            ->setMaxLineLength($maxLineLength);
        $descs[0] = $this->createFakeDesc(' * ', $generator);
        $secondTable = [
            [ '@param', 'string', '$name' ], [ '@param', 'array',  '$longger' ]
        ];
        $threeColumns = ' * ' . join(' ', $secondTable[0]) . '    ';
        $descs[] = $this->createFakeDesc($threeColumns, $generator);
        $descs[] = $this->createFakeDesc($threeColumns, $generator);
        $thirdTable = [ '@return', 'string' ];
        $twoColumns = ' * ' . join(' ', $thirdTable) . ' ';
        $descs[] = $this->createFakeDesc($twoColumns, $generator);
        
        $array = $this->createArray($descs, $secondTable, $thirdTable);
        $expected = $this->createExpected(
            $array, $descs, $threeColumns, $twoColumns, $indent
        );
        return [ $array, $expected, $generator ];
    }

    public function createFakeDesc(
        string $base, PhpdocGenerator $generator
    ): array {
        $offset = strlen($base);
        $maxLineLength = $generator->getMaxLineLength();
        $indentation = $generator->getIndentation();
        $minLastColumnWidth = $generator->getMinLastColumnWidth();
        $widthCalculated = $maxLineLength - $indentation - $offset;
        $width = ($widthCalculated < $minLastColumnWidth) ? $minLastColumnWidth
            : $widthCalculated;
        
        return $this->createRandomParagraph($width);
    }

    public function createRandomParagraph(int $width): array {
        $lines = $remains = [];
        [ $remains[0], $lines[0] ] = $this->fillWithWords($width);

        [ $remains[1], $lines[1] ] = $this->fillWithWords($width, $remains[0]);
        $lines[1] .= ' ' . $this->createRandomWord($remains[1]);

        [ $remains[2], $lines[2] ] = $this->fillWithWords($width);

        $lines[3] = $this->fillWithWords(20, $remains[2])[1];

        return $lines;
    }

    function fillWithWords(int $upTo, ?int $startWidth = null): array
    {
        $remaining = $upTo;
        $line = '';
        while ($remaining > 14) {
            if (is_null($startWidth)) {
                $length = random_int(1, 12);
            } else {
                $length = $startWidth + random_int(1, 4);
                $startWitht = null;
            }
            $line .= $this->createRandomWord($length) . ' ';
            $remaining = $remaining - $length - 1;
        }

        $line = rtrim($line);
        return [ $remaining, $line ];
    }
    
    public function createRandomWord(int $length): string {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $randomWord = '';

        for ($i = 1; $i <= $length; $i++) {
            $index = random_int(0, strlen($characters) - 1);
            $randomWord .= $characters[$index];
        }

        return $randomWord;
    }

    public function createArray(
        array $descs, array $secondTable, array $thirdTable
    ): array {
        $secondTable[0][3] = join(' ', $descs[1]);
        $secondTable[1][3] = join(' ', $descs[2]);
        $thirdTable[2] = join(' ', $descs[3]);

        return [ join(' ', $descs[0]), $secondTable, [ $thirdTable ] ];
    }

    public function createExpected(
        array $array, array $descs, string $threeColumns, string $twoColumns,
        int $indent
    ): string {
        $wraps = [];
        $wraps[] = $this->createWordwrap($descs[0], ' * ', $indent);
        $wraps[] = $this->createWordwrap($descs[1], $threeColumns, $indent);
        $wraps[] = $this->createWordwrap($descs[2], $threeColumns, $indent);
        $wraps[] = $this->createWordwrap($descs[3], $twoColumns, $indent);
        $expected = "/**\n * " . $wraps[0] . "\n *\n" . $threeColumns
            . $wraps[1] . "\n * " . $array[1][1][0] . ' '
            . $array[1][1][1] . '  ' . $array[1][1][2] . ' '
            . $wraps[2] . "\n *\n" . $twoColumns . $wraps[3] . "\n */";
        
        return $this->indentContent($indent, $expected);
    }

    public function createWordwrap(
        array $desc, string $base
    ): string {
        $width = strlen($base) - strlen(' * ');
        $separator = ' * ' . str_repeat(' ', $width);
        return join("\n$separator", $desc);
    }

    #[Test]
    public function a_diferent_max_line_length_can_be_set(): void
    {
        $maxLineLength = 120;
        
        [ $array, $expected, $generator ] = $this->exampleThree(
            6, $maxLineLength
        );

        $this->assertEquals($maxLineLength, $generator->getMaxLineLength());

        $actual = $generator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function last_column_has_a_minimum_width(): void
    {
        [ $array, $expected, $generator ] = $this->exampleFour();

        $actual = $generator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    public function exampleFour(?int $minLastColumnWidth = null): array
    {
        $indent = 4;
        $generator = (new PhpdocGenerator)->setIndentation($indent);
        (!is_null($minLastColumnWidth)) && $generator
            ->setMinLastColumnWidth($minLastColumnWidth);
        $exampleTable = [
            '@param', 'null|int|float|array|Countable',
            '$thisWillLeaveVeryLittleSpaceForTheLastColumn'
        ];
        $threeColumns = ' * ' . join(' ', $exampleTable) . ' ';
        $desc = $this->createFakeDesc($threeColumns, $generator);
        $exampleTable[] = join(' ', $desc);
        $array = [[ $exampleTable ]];

        $wrap = $this->createWordwrap($desc, $threeColumns, $indent);
        $expected = "/**\n" . $threeColumns . $wrap . "\n */";
        $expected = $this->indentContent($indent, $expected);
        return [ $array, $expected, $generator ];
    }

    #[Test]
    public function a_min_last_column_length_can_be_stablish(): void
    {
        $minLastColumnWidth = 25;
        
        [ $array, $expected, $generator ] = $this->exampleFour(
            $minLastColumnWidth
        );

        $this->assertEquals(
            $minLastColumnWidth, $generator->getMinLastColumnWidth()
        );

        $actual = $generator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }
}
