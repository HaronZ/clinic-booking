<?php
declare(strict_types=1);

namespace Clinic\Tests\Unit;

use Clinic\Util\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testBasicAsciiNameLowercased(): void
    {
        $this->assertSame('dr-ana-reyes', Slug::fromName('Dr. Ana Reyes'));
    }

    public function testSpanishAccentsAreFolded(): void
    {
        $this->assertSame('dr-ramon-nunez', Slug::fromName('Dr. Ramón Núñez'));
    }

    public function testFrenchAccentsAreFolded(): void
    {
        $this->assertSame('dr-noel-cafe', Slug::fromName('Dr. Noël Café'));
    }

    public function testParenthesesAndOtherSymbolsBecomeHyphens(): void
    {
        $this->assertSame('annual-physical-60m', Slug::fromName('Annual Physical (60m)'));
    }

    public function testMultipleSpacesAndHyphensCollapse(): void
    {
        $this->assertSame('multiple-spaces', Slug::fromName('  multiple   spaces  '));
    }

    public function testLeadingAndTrailingHyphensTrimmed(): void
    {
        $this->assertSame('hello', Slug::fromName('---hello---'));
    }

    public function testEmptyInputProducesFallback(): void
    {
        $this->assertSame('item', Slug::fromName(''));
        $this->assertSame('item', Slug::fromName('!!!'));
        $this->assertSame('item', Slug::fromName('   '));
    }

    public function testNumbersArePreserved(): void
    {
        $this->assertSame('clinic-2024-q1', Slug::fromName('Clinic 2024 Q1'));
    }
}
