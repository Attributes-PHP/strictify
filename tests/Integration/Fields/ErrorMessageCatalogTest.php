<?php

declare(strict_types=1);

namespace Attributes\Strictify\Tests\Integration\Fields;

use Attributes\Strictify\BaseModel;
use Attributes\Strictify\Exceptions\ValidationException;
use Attributes\Strictify\Fields\Constraints\ContainsAny;
use Attributes\Strictify\Fields\Constraints\Digit;
use Attributes\Strictify\Fields\Constraints\Iban;
use Attributes\Strictify\Fields\ErrorMessage;
use Attributes\Strictify\Internal\ErrorMessages;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionProperty;

use function Attributes\Strictify\validate;

/*
 * Every default-catalog key the attribute accepts must be a public
 * ErrorMessage property with the same default template, including the
 * constraints added later (Email, Url, Iban, ...). The `extra` key has no
 * owning property and is not an ErrorMessage argument.
 */
it('exposes every catalog key with the runtime default', function () {
    $defaults = (new ReflectionClassConstant(ErrorMessages::class, 'DEFAULTS'))->getValue();
    unset($defaults[ErrorMessages::EXTRA]);

    $reflection = new ReflectionClass(ErrorMessage::class);
    $runtime = [];
    foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        $runtime[$property->getName()] = $property->getDefaultValue();
    }

    expect($runtime)
        ->not
        ->toBeEmpty()
        ->and(array_keys($runtime))
        ->toEqualCanonicalizing(array_keys($defaults))
        ->and($runtime)
        ->toBe($defaults);
});

it('overrides the message of the constraints added with the parity batch', function (string $key, string $expected) {
    $model = match ($key) {
        'digit' => new class extends BaseModel {
            #[Digit]
            #[ErrorMessage(digit: 'Only digits, please')]
            public string $value;
        },
        'iban' => new class extends BaseModel {
            #[Iban]
            #[ErrorMessage(iban: 'Bad IBAN')]
            public string $value;
        },
        'contains_any' => new class extends BaseModel {
            #[ContainsAny('x')]
            #[ErrorMessage(containsAny: 'Nothing matched')]
            public array $value;
        },
    };

    try {
        validate(['value' => $key === 'contains_any' ? ['a'] : 'zz'], $model);
        expect(false)->toBeTrue('ValidationException expected');
    } catch (ValidationException $e) {
        expect($e->getErrors())->toBe(['value' => [$expected]]);
    }
})->with([
    ['digit',        'Only digits, please'],
    ['iban',         'Bad IBAN'],
    ['contains_any', 'Nothing matched'],
]);
