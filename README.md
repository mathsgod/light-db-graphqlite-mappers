# light-db-graphqlite-mappers

A [GraphQLite](https://graphqlite.thecodingmachine.io/) type mapper that exposes `Light\Db\Query` objects as paginated GraphQL types with metadata.

## Installation

```bash
composer require mathsgod/light-db-graphqlite-mappers:^2.0
```

## What it does

When a GraphQL field returns a `Light\Db\Query` instance, this mapper automatically wraps it into a structured type with two fields:

| Field  | Type        | Description                                      |
|--------|-------------|--------------------------------------------------|
| `data` | `[T!]!`     | The list of records, supports `limit` / `offset` |
| `meta` | `DB_META!`  | Metadata about the query result                  |

### `DB_META` fields

| Field   | Type     | Description                    |
|---------|----------|--------------------------------|
| `total` | `Int`    | Total number of matching records |
| `class` | `String` | Fully qualified class name     |
| `key`   | `String` | Primary key field name         |
| `name`  | `String` | Short class name               |

## Usage

Register the type mapper with your GraphQLite `SchemaFactory`:

```php
use Light\Db\GraphQLite\Mappers\TypeMapper;

$schemaFactory->addTypeMapper(new TypeMapper($factoryContext));
```

Then return a `Light\Db\Query` from any GraphQL query method and type-hint the sub-type:

```php
use TheCodingMachine\GraphQLite\Annotations\Query;
use Light\Db\Query;

#[Query]
public function users(): Query /* <User> */
{
    return User::query();
}
```

The generated GraphQL type will be `DB_QUERY_User` with `data` and `meta` fields.

### Example GraphQL query

```graphql
{
    users {
        data(limit: 10, offset: 0) {
            id
            name
        }
        meta {
            total
            key
        }
    }
}
```

## Requirements

- PHP 8.1+
- `thecodingmachine/graphqlite` ^8.0
- `mathsgod/light-db`

## License

MIT

## Version 2

Requires PHP 8.3+, light-db 2.x (light-entity based) and GraphQLite 8.x.
While preparing the release, the Composer path repository resolves the sibling
`../light-db-new` checkout as `2.0.x-dev`. Remove that repository and the `@dev`
flag when publishing against a stable light-db 2 release.

Pagination retains the returned immutable Query, so multiple aliases can request
independent pages. `meta.total` counts matches before pagination. For composite
keys, `meta.key` contains comma-separated key names. GraphQL field shapes remain
unchanged. Offset requires an explicit limit; negative limits/offsets are rejected.
