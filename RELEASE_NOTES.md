# Release notes

## 1.0.0 — 2026-11-27

Strictify 1.0.0 is the first stable release, and it is a different library from the 0.9 line. The pre-1.0 `attributes-php/validation` engine — respect/validation rules, `attributes-php/options`, `Context`, a configurable `Validator` chain — is gone. What ships now is a pure-PHP port of the [Strictify Extension](https://github.com/Attributes-PHP/strictify-ext) API: two functions, one base class, attributes on your properties, and the type hints you already wrote as the schema.

- **Package:** `attributes-php/validation` → `attributes-php/strictify`
- **Namespace:** `Attributes\Validation` → `Attributes\Strictify`
- **PHP:** 8.1 → 8.2 minimum
- **Runtime dependencies:** two → none
- **Tests:** 1,741 passing, 2,302 assertions on PHP 8.2, the minimum supported version

```php
use Attributes\Strictify\BaseModel;
use Attributes\Strictify\Fields\Constraints\{Between, Email, In};

use function Attributes\Strictify\validate;

class Signup extends BaseModel
{
    public string $name;

    #[Email]
    public string $email;

    #[Between(18, 99)]
    public int $age;

    #[In('free', 'pro')]
    public string $plan = 'free';

    public ?DateTime $birthday = null;
}

$signup = validate([
    'name' => 'Andre',
    'email' => 'andre@example.com',
    'age' => 30,
    'birthday' => '1994-01-01T09:00:00+00:00',
], new Signup());

$signup->birthday;        // DateTime
$signup->toDict();
// ['name' => 'Andre', 'email' => 'andre@example.com', 'age' => 30,
//  'plan' => 'free', 'birthday' => '1994-01-01T09:00:00+00:00']
```

## Highlights

- `validate()` hydrates a model, `call()` validates a callable's parameters and injects the rest — no `new Validator()` to wire up.
- 38 built-in constraint attributes, so validation rules no longer come from a third-party rule set.
- Nested shapes on real type hints: `#[Sequence]`, `#[Dict]`, `#[Union]`, `#[Intersection]`, nestable and recursive.
- Per-model configuration through `#[ModelConfigs]`: strict/loose scalars, alias generators, string normalization, extra-key policy, stop-at-first-error.
- `beforeValidation` / `afterValidation` hooks, `toDict()` with alias and sensitive-data handling, `#[Sensitive]` masking in debug output.
- Translatable error catalog: stable camelCase keys, per-property `#[ErrorMessage]` overrides, `ErrorTranslator`.
- Every error the library raises implements `Exceptions\StrictifyThrowable`.
- One catchable exception, `MaxDepthException`, instead of a stack overflow on hostile payloads.

## Install or upgrade

```bash
composer require attributes-php/strictify
```

```bash
composer remove attributes-php/validation
composer require attributes-php/strictify
```

The native extension is a drop-in alternative with the same API, behavior and documentation, for when you want the speed:

```bash
pie install attributes-php/strictify-ext
```

## Migrating from `attributes-php/validation` 0.9

| | 0.9 | 1.0 |
| --- | --- | --- |
| Package | `attributes-php/validation` | `attributes-php/strictify` |
| Namespace | `Attributes\Validation` | `Attributes\Strictify` |
| Entry point | `(new Validator())->validate($data, $model)` | `validate($data, $model)` |
| Callables | `$validator->validateCallable($data, $fn)` | `call($fn, $params, $dependencies)` |
| Model base | none (plain classes) | `extends BaseModel` |
| Rules | Respect\Validation attributes (`#[Rules\Min(0)]`) | built-in attributes (`#[Min(0)]`) |
| Configuration | `new Validator(stopFirstError: …, strict: …)`, `Context` | `#[ModelConfigs(…)]` on the model |
| Input | `array`, `ArrayObject`, or a class name | `array`, and a `BaseModel` instance |
| Scalar coercion | on by default (`strict: false`) | **off** by default (`strict: true` in the default `ModelConfigs`) |
| PHP | 8.1+ | 8.2+ |
| Runtime deps | `respect/validation`, `attributes-php/options` | none |

```php
// 0.9
use Attributes\Validation\Validator;
use Respect\Validation\Rules;

class Person
{
    #[Rules\Min(0)]
    public float|int $age;
}

$person = (new Validator())->validate($rawData, new Person);

// 1.0
use Attributes\Strictify\BaseModel;
use Attributes\Strictify\Fields\Constraints\Min;

use function Attributes\Strictify\validate;

class Person extends BaseModel
{
    #[Min(0)]
    public float|int $age;
}

$person = validate($rawData, new Person());
```

### Breaking changes

These all follow from the rewrite, plus the changes that landed between the extension split and 1.0:

- The old engine is removed, not deprecated: `Validator`, `Validatable`, `Context`, `Property`, `ErrorHolder`, the `Types\*` and `Validators\*` classes, and the `StopValidationException` / `ContinueValidationException` flow. Constraints are returning-`bool` attribute classes now; stop-at-first-error is `ModelConfigs(stopAtFirstError: true)`.
- Exception hierarchy follows PHP's data-error / programming-error split. `ValidationException` extends `\Exception`; `InvalidBaseModelException` and `MaxDepthException` extend `\Error`; `CallException` extends `\ArgumentCountError`. `BaseException` is removed — catch `Exceptions\StrictifyThrowable` to cover all four. A `catch (\Exception $e)` no longer swallows a malformed model.
- A malformed model declaration always throws `InvalidBaseModelException`, never `ValueError` or `TypeError`: inverted or `NAN` numeric bounds, an inverted `Size` range, an empty `In` / `StartsWith` / `EndsWith` list, a `Regex` that does not compile, two properties resolving to the same input key, an empty `#[Alias('')]`, `#[Sequence]` on a non-array property, `#[Union]` that does not repeat the native hint's arms.
- The type constants carry a `T_` prefix so they stop colliding with PHP's reserved type-hint words: `T_BOOL`, `T_INT`, `T_FLOAT`, `T_STRING`, `T_NULL`, `T_SEQUENCE`, `T_DICT`, `T_OBJECT`. The unprefixed names still resolve, but new code should import the `T_*` ones.
- The default message translator moved off `ModelConfigs` onto `ErrorTranslator::set()` / `get()`. The callable is now `fn (?string $key, string $message): ?string`, with `null` as the key for custom `#[ErrorMessage]` text.
- Error catalog keys are camelCase everywhere — `#[ErrorMessage]` arguments and the key handed to a translator: `alphaDash`, `sequenceKeys`, `uniqueValues`, `urlProtocol`. A snake_case key no longer matches.
- `#[ModelConfigs]` is inherited through the class hierarchy only. A nested model does not inherit its parent's configs; `aliasGenerator`, `strict`, `extra`, the string normalizations and `stopAtFirstError` each resolve per model class.
- `toDict()` keys by property name by default. Pass `useAlias: true` for alias keys, including per-model generators through nested models.
- `#[Sequence]` requires integer keys and is sequential by default (keys must run `0, 1, 2, …`); `sequential: false` allows gaps. The `Sorted` constraint is gone, replaced by `#[Sequence(sorted: 'asc'|'desc')]`.
- `#[Union]` on a union-typed property must list the native hint's arms exactly. A subset or an extra arm throws instead of narrowing or widening; a nullable hint needs `T_NULL` in the list.
- Constraints are skipped for an explicit `null`, the same as a missing key.
- `#[Sensitive]` properties are masked as `***` in `var_dump()`, `print_r()` and exception traces; property access, `json_encode()` and `serialize()` keep the real values.

## What's new

### `validate()` and `call()`

Both are plain functions, and both take an optional `$maxDepth` (`512` by default):

```php
validate(array $rawData, BaseModel $model, int $maxDepth = 512): BaseModel
call(string|callable $function, array $params, array|ArrayAccess $dependencies = [], int $maxDepth = 512): mixed
```

`call()` hydrates every `BaseModel` parameter of the target from `$params` through the same engine, and takes everything else from `$dependencies`, which may be a plain array or any `ArrayAccess` container. It respects `#[\SensitiveParameter]` on the target: a missing one is reported positionally and never appears in a trace.

```php
use function Attributes\Strictify\call;

function notify(Signup $user, Mailer $mailer): void { /* ... */ }

call('notify', $payload, ['mailer' => $container->get(Mailer::class)]);
```

Dependencies are matched by parameter name first, then in positional order, so a plain `[$container->get(Mailer::class)]` works too.

### Type hints are the schema

Nested models, enums (backed and pure, with their cases listed in the error message), `DateTimeInterface`, `array`, `object`, unions, intersections, `mixed`, `callable`, nullable hints, defaults, and inheriting models declared across a class hierarchy. In strict mode the check mirrors PHP's `strict_types`; in loose mode it mirrors PHP's weak coercion, float-precision deprecations included. Either way an `int` satisfies a `float` hint, because PHP does that too.

```php
#[ModelConfigs(strict: false)]
class Form extends BaseModel
{
    public bool $active;      // 'yes', 'on', 1 ...
    public int $age;          // '30'
    public float $balance;    // '1250.50'
}
```

### Array shapes

```php
use Attributes\Strictify\Fields\{Dict, Intersection, Sequence, Union};

use const Attributes\Strictify\{T_INT, T_NULL, T_STRING};

#[Sequence(of: T_STRING)]                                    public array $tags;      // 0, 1, 2, …
#[Sequence(of: T_INT, uniqueValues: true, sorted: 'asc')]    public array $scores;
#[Sequence(of: Post::class)]                                 public array $posts;     // nested models
#[Sequence(of: new Union(T_INT, T_STRING))]                  public array $ids;
#[Dict(key: T_INT, of: T_STRING)]                            public array $labels;
#[Dict(key: new Union(T_INT, T_NULL), of: new Sequence(of: T_INT))] public array $matrix;
#[Intersection(Countable::class, ArrayAccess::class)]        public array $collection;
#[Union(T_INT, T_STRING, T_NULL)]                            public int|string|null $id;
```

`Dict` keys accept every PHP-castable key type (float and bool keys fold to int, `null` to `""`), matching the engine's own key casting. Errors are reported at dotted paths, so a bad element inside a nested shape keeps its location: `posts.0.title`.

### Constraint attributes

38 of them, each with its own default message and translation key:

`Alnum` `Alpha` `AlphaDash` `Ascii` `Base64` `Between` `Contains` `ContainsAny` `CreditCard` `DateTime` `Digit` `Domain` `Email` `EndsWith` `Even` `HexRgbColor` `Iban` `In` `Ip` `Isbn` `Json` `MacAddress` `Max` `Min` `MultipleOf` `Negative` `Odd` `Positive` `PrimeNumber` `Regex` `Size` `Slug` `StartsWith` `Ulid` `Url` `Uuid` `Version` `Xdigit`

```php
#[Email(domain: ['example.com', 'example.org'])]
#[Url(protocol: 'https', domain: 'example.com')]
#[Ip(version: 'v4')]
#[Between(18, 99)]
#[ContainsAny('admin', 'owner')]
#[DateTime('Y-m-d', 'UTC')]
#[Size(min: 3, max: 20)]
```

Restricted variants add keys instead of new attributes: `emailDomain`, `urlProtocol`, `urlDomain`.

Write your own by implementing `Fields\Constraint` (`validate(mixed $value): bool` and `message(): string`), or `ConfigAwareConstraint` when the rule needs the resolved `ModelConfigs`.

### Field attributes

```php
use Attributes\Strictify\Fields\{Alias, ErrorMessage, Sensitive};

#[Alias('user_name')]
public string $name;

#[Sensitive]
public string $password;

#[ErrorMessage(required: 'Name is mandatory', min: 'At least {expected}')]
public string $nickname;
```

`#[ErrorMessage]` takes every key of the default catalog as a named argument, `required`, `type`, `alphaDash`, `between`, `min`, `max`, `sequenceKeys` and the rest, and defaults to each key's message.

### `#[ModelConfigs]`

```php
use Attributes\Strictify\ModelConfigs;

#[ModelConfigs(
    aliasGenerator: 'snake',   // pascal, camel, snake, kebab
    strToLower: false,
    strToUpper: false,
    stripWhitespace: true,
    extra: 'forbid',           // ignore, forbid, allow
    strict: true,
    stopAtFirstError: false,
)]
class User extends BaseModel {}
```

`extra: 'forbid'` reports unknown input keys, `'allow'` stores them as dynamic properties, `'ignore'` drops them. Option values are type-checked, not cast: `strict: 1` throws.

### Hooks

```php
protected function beforeValidation(array $rawData, ModelConfigs $configs): array
protected function afterValidation(array $rawData, ModelConfigs $configs): void
```

`beforeValidation` may rewrite the payload, `afterValidation` runs once the model is populated. Models that declare neither pay nothing — the check is cached per class.

### Serialization

```php
$user->toDict();               // ['name' => 'John']
$user->toDict(useAlias: true); // ['user_name' => 'John']
$user->toDict(includeSensitive: true);
```

Public, initialized properties only. `DateTimeInterface` values are formatted with the property's `#[DateTime]` format and timezone, nested models serialize with their own generator, and alias output round-trips back through `validate()`.

### Errors

```php
use Attributes\Strictify\Exceptions\ValidationException;

try {
    validate($payload, new User());
} catch (ValidationException $e) {
    $e->getErrors(); // ['email' => ['Must be a valid email address']]
}
```

`ErrorTranslator::set(fn (?string $key, string $message): ?string => …)` translates templates by stable key, and registering one drops the per-property message cache so it takes effect immediately.

### Safety

- A payload nesting models or shapes more than 512 levels deep throws `MaxDepthException` instead of exhausting the stack; siblings do not add depth, and the counter unwinds on every exit path. Lower or raise it per call with `maxDepth:`.
- Malformed declarations fail at first compile with a message naming the attribute, model and property — before any data is validated.
- Alias collisions are a hard error: two properties resolving to one input key, or an empty alias, used to produce a field that could never be satisfied.

## Performance

Type hints are compiled once per property into plans, cached per class and alias generator along with field names, defaults, constraint instances and hook checks; values are written directly instead of through `ReflectionProperty::setValue()`. Steady-state cost of the benchmark scenarios in `tests/Benchmark`, microseconds per validation, same input data for every implementation:

| Scenario | Strictify | Symfony Validator | Laravel Validator |
| --- | --- | --- | --- |
| 5-field model | 8.7 | 13.7 | 28.4 |
| 15 fields, 15 constraints | 30.5 | 55.0 | 220.6 |
| Nested model | 63.2 | 73.1 | 206.0 |
| 32-field model | 16.6 | 77.3 | 161.9 |
| Nested sequences | 313.7 | 1,810.0 | 8,785.8 |

Measured on one machine with 5 iterations; treat it as a rough relative guide, not a guarantee. Each implementation does its idiomatic work — Strictify hydrates a model, the others return violation lists. The respect/validation suite is excluded from the table: its benchmark only returns a bool without building messages or hydrating.

```bash
composer benchmark
```

## Compatibility

- PHP 8.2 or later, no extensions beyond the defaults.
- PHP 8.4 property hooks: writes go through `set` hooks, virtual properties are skipped, `toDict()` reads through `get` hooks.
- Same API as [Strictify Extension](https://github.com/Attributes-PHP/strictify-ext); switching between the two needs no code changes.

## Documentation

- [Quickstart](https://strictify.attributes-php.com/quickstart/) and [Guides](https://strictify.attributes-php.com/guides/validating-payloads/)
- [Reference](https://strictify.attributes-php.com/validate/) for `validate()`, `toDict()`, `call()`, `ModelConfigs`, constraints, exceptions
- [Changelog](https://strictify.attributes-php.com/changelog/) for the itemized list of everything that changed
- [llms.txt](https://strictify.attributes-php.com/llms.txt) for agents

---

Strictify was created by **[André Gil](https://www.linkedin.com/in/andre-gil/)** and is open-sourced software licensed under the [MIT license](LICENSE).
