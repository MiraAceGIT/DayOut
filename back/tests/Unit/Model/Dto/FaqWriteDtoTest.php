<?php
declare(strict_types=1);

namespace Tests\Unit\Model\Dto;

use App\Core\ValidationException;
use App\Model\Dto\Faq\FaqCreateDto;
use App\Model\Dto\Faq\FaqUpdateDto;
use Tests\TestCase;

final class FaqWriteDtoTest extends TestCase
{
    public function testRejectsInvalidCreateAndUpdateFields(): void
    {
        foreach ([
            ['question' => ' '], ['question' => null], ['question' => str_repeat('q', 256)], ['question' => []],
            ['answer' => ''], ['answer' => null], ['answer' => str_repeat('r', 16001)],
            ['sort_order' => -1], ['sort_order' => '4294967296'], ['sort_order' => null],
            ['active' => 'yes'], ['active' => null],
        ] as $input) {
            foreach ([FaqCreateDto::class, FaqUpdateDto::class] as $class) {
                $body = $class === FaqCreateDto::class ? $input + ['question' => 'Q', 'answer' => 'R'] : $input;
                $error = $this->assertThrows(ValidationException::class, fn () => $class::fromArray($body));
                $this->assertSame(array_keys($input), array_keys($error->errors));
            }
        }
    }

    public function testRequiresContentAndAtLeastOneWritableField(): void
    {
        $this->assertThrows(ValidationException::class, fn () => FaqCreateDto::fromArray([]));
        foreach ([[], ['id' => 1], ['created_at' => '2030-01-01']] as $input) {
            $error = $this->assertThrows(ValidationException::class, fn () => FaqUpdateDto::fromArray($input));
            $this->assertSame(['body'], array_keys($error->errors));
        }
    }
}
