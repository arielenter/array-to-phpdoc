<?php

namespace Tests\Unit;

use Arielenter\ArrayToPhpdoc\DocBlockCreator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocBlockCreatorTest extends TestCase
{
    #[Test]
    public function creates_a_doc_block_from_an_array(): void
    {
        [ $array, $expected ] = $this->exampleOne();

        $actual = (new DocBlockCreator)->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array{array,string}
     */
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

        $actual = (new DocBlockCreator)->fromArray($array);

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

        $actual = (new DocBlockCreator)->fromArray($arrayWithStringKeys);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function a_single_line_doc_block_is_created_if_it_fits_in_one_line(
    ): void
    {
        $array = ['example' => [ '@var', 'int', 'Very short description.' ]];
        $expected = '/** ' . join(' ', $array['example']) . ' */';
        $docCreator = new DocBlockCreator;
        $actual = $docCreator->fromArray($array);
        $this->assertEquals($expected, $actual);

        $this->singleLineExampleTwo($docCreator);

        $this->notAValidSingleLineExample($docCreator);
    }

    public function singleLineExampleTwo(DocBlockCreator $docCreator): void
    {
        $array = [ 'Short desctiption.' ];
        $expected = '/** ' . join(' ', $array) . ' */';
        $actual = $docCreator->fromArray($array);
        $this->assertEquals($expected, $actual);
    }

    public function notAValidSingleLineExample(
        DocBlockCreator $docCreator
    ): void {
        // Just a single character too long
        $array = [ 'Very ' . str_repeat('long ', 12) . 'to fit...' ];
        $expected = "/**\n * " . $array[0] . "\n */";
        $actual = $docCreator->fromArray($array);
        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function an_indentation_can_be_set(): void
    {
        $indentWidth = 6;

        [ $array, $expected, $docCreator ] = $this->exampleTwo($indentWidth);

        $this->assertEquals($indentWidth, $docCreator->getIndentWidth());

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function exampleTwo(
        int $indentWidth = 4, bool $useTab = false
    ): array {
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

        $docCreator = (new DocBlockCreator)->setIndentWidth($indentWidth);
        ($useTab == true) && $docCreator->setUseTabForIndentation(true);

        $expected = $this->indentContent($indentWidth, $expected, $useTab);

        return [ $array, $expected, $docCreator ];
    }

    public function indentContent(
        int $indentWidth, string $expected, bool $useTab = false
    ): string
    {
        $indentStr = $this->indentStr($indentWidth, $useTab);
        return $indentStr . str_replace("\n", "\n$indentStr", $expected);
    }

    public function indentStr(int $indentWidth, bool $useTab): string
    {
        if ($useTab == true) {
            return "\t";
        }
        return str_repeat(' ', $indentWidth);
    }

    #[Test]
    public function tab_can_be_used_for_indentation(): void
    {
        [ $array, $expected, $docCreator ] = $this->exampleTwo(useTab: true);

        $this->assertTrue($docCreator->getUseTabForIndentation());

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);        
    }


    #[Test]
    public function sngl_row_multi_cols_tbls_may_be_given_unnested(): void
    {
        [ $array, $expected, $docCreator ] = $this->exampleTwo();

        $array[2] = $array[2][0];

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function last_column_of_a_row_can_be_omitted(): void
    {
        [ $array, $expected, $docCreator ] = $this->exampleTwo();

        $expected = str_replace(' ' . $array[1][1][3], '', $expected);
        unset($array[1][1][3]);
        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function last_column_is_wrap(): void
    {
        [ $array, $expected, $docCreator ] = $this->exampleThree();

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function exampleThree(
        int $indentWidth = 4, ?int $maxLength = null, bool $useTab = false
    ): array {
        $descs = [];
        $docCreator = (new DocBlockCreator)->setIndentWidth($indentWidth)
            ->setUseTabForIndentation($useTab);
        (!is_null($maxLength)) && $docCreator->setMaxLineLength($maxLength);
        
        $descs[0] = $this->createFakeDesc(' * ', $docCreator);
        $secondTable = [
            [ '@param', 'string', '$name' ], [ '@param', 'array',  '$longger' ]
        ];
        $threeColumns = ' * ' . join(' ', $secondTable[0]) . '    ';
        $descs[] = $this->createFakeDesc($threeColumns, $docCreator);
        $descs[] = $this->createFakeDesc($threeColumns, $docCreator);
        $thirdTable = [ '@return', 'string' ];
        $twoColumns = ' * ' . join(' ', $thirdTable) . ' ';
        $descs[] = $this->createFakeDesc($twoColumns, $docCreator);

        $array = $this->createArray($descs, $secondTable, $thirdTable);
        $expected = $this->createExpected(
            $array, $descs, $threeColumns, $twoColumns, $indentWidth, $useTab
        );
        return [ $array, $expected, $docCreator ];
    }

    public function createFakeDesc(
        string $base, DocBlockCreator $docCreator
    ): array {
        $offset = strlen($base);
        $maxLineLength = $docCreator->getMaxLineLength();
        $indentWidth = $docCreator->getIndentWidth();
        $minLastColumnWidth = $docCreator->getMinLastColumnWidth();
        $widthCalculated = $maxLineLength - $indentWidth - $offset;
        $width = ($widthCalculated < $minLastColumnWidth) ? $minLastColumnWidth
            : $widthCalculated;

        return $this->createRandomParagraph($width);
    }

    public function createRandomParagraph(int $width): array {
        $lines = $remains = [];
        [ $remains[0], $lines[0] ] = $this->fillWithWords($width);

        [ $remains[1], $lines[1] ] = $this->fillWithWords($width, $remains[0]);
        if ($remains[1] > 0) {
            $lines[1] .= ' ' . $this->createRandomWord($remains[1]);
        }

        [ $remains[2], $lines[2] ] = $this->fillWithWords($width);

        $lines[3] = $this->fillWithWords(20, $remains[2])[1];

        return $lines;
    }

    /**
     * @return array{int,array}
     */
    function fillWithWords(int $upTo, ?int $start = null): array
    {
        $max = 12;
        $start ??= random_int(0, $max - 5);
        $length = $start + random_int(1, 4);
        $remaining = $upTo;
        $line = '';
        while ($remaining >= $max + 1) {
            $line .= $this->createRandomWord($length) . ' ';
            $remaining -= ($length + 1);
            $length = random_int(1, $max);
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

    /**
     * @param array<int,string> $descs
     * @param array<int,array>  $secondTable
     * @param array<int,string> $thirdTable
     *
     * @return array{string,array,array}
     */
    public function createArray(
        array $descs, array $secondTable, array $thirdTable
    ): array {
        $secondTable[0][3] = join(' ', $descs[1]);
        $secondTable[1][3] = join(' ', $descs[2]);
        $thirdTable[2] = join(' ', $descs[3]);

        return [ join(' ', $descs[0]), $secondTable, [ $thirdTable ] ];
    }

    /**
     * @param array $array
     * @param array $descs
     */
    public function createExpected(
        array $array, array $descs, string $threeColumns, string $twoColumns,
        int $indent, bool $useTab
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

        return $this->indentContent($indent, $expected, $useTab);
    }

    /**
     * @param array<int,string> $desc
     */
    public function createWordwrap(
        array $desc, string $base
    ): string {
        $width = strlen($base) - strlen(' * ');
        $separator = ' * ' . str_repeat(' ', $width);
        return join("\n$separator", $desc);
    }

    #[Test]
    public function tab_indented_is_wraped_accordently(): void
    {
        [ $array, $expected, $docCreator ] = $this->exampleThree(useTab: true);

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function a_diferent_max_line_length_can_be_set(): void
    {
        $maxLength = 120;

        [ $array, $expected, $docCreator ] = $this->exampleThree(6, $maxLength);

        $this->assertEquals($maxLength, $docCreator->getMaxLineLength());

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);

        $this->withTabIndentationExample();
    }

    public function withTabIndentationExample(): void
    {
        $maxLength = 120;

        [ $array, $expected, $docCreator ] = $this
            ->exampleThree(6, $maxLength, true);

        $this->assertEquals($maxLength, $docCreator->getMaxLineLength());

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);        
    }

    #[Test]
    public function last_column_has_a_minimum_width(): void
    {
        [ $array, $expected, $docCreator ] = $this->exampleFour();

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public function exampleFour(?int $minLastColumnWidth = null): array
    {
        $indent = 4;
        $docCreator = (new DocBlockCreator)->setIndentWidth($indent);
        (!is_null($minLastColumnWidth)) && $docCreator
            ->setMinLastColumnWidth($minLastColumnWidth);
        $exampleTable = [
            '@param', 'null|int|float|array|Countable',
            '$thisWillLeaveVeryLittleSpaceForTheLastColumn'
        ];
        $threeColumns = ' * ' . join(' ', $exampleTable) . ' ';
        $desc = $this->createFakeDesc($threeColumns, $docCreator);
        $exampleTable[] = join(' ', $desc);
        $array = [[ $exampleTable ]];

        $wrap = $this->createWordwrap($desc, $threeColumns, $indent);
        $expected = "/**\n" . $threeColumns . $wrap . "\n */";
        $expected = $this->indentContent($indent, $expected);
        return [ $array, $expected, $docCreator ];
    }

    #[Test]
    public function a_min_last_column_length_can_be_stablish(): void
    {
        $minWidth = 25;

        [ $array, $expected, $docCreator ] = $this->exampleFour($minWidth);

        $this->assertEquals($minWidth, $docCreator->getMinLastColumnWidth());

        $actual = $docCreator->fromArray($array);

        $this->assertEquals($expected, $actual);
    }
}
