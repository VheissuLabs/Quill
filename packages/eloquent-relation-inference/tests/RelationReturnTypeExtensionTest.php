<?php

declare(strict_types=1);

namespace Quill\EloquentRelationInference\Tests;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RelationReturnTypeExtensionTest extends TypeInferenceTestCase
{
    /** @return iterable<mixed> */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__.'/Fixtures/relation-types.php');
    }

    #[DataProvider('dataFileAsserts')]
    public function test_file_asserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /** @return array<int, string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../extension.neon'];
    }
}
