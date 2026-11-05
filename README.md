# light-db-graphqlite-mappers

A GraphQLite type mapper that exposes light-db 2 Query results as paginated
GraphQL lists with metadata. Requires PHP 8.3+, GraphQLite 8.x and light-db 2.x,
backed by light-entity 1.x.

For upgrades from the Laminas-based integration, see the
[v2 migration guide](docs/migration-v2.md). The previous implementation is
preserved on the `1.x` maintenance branch.

## Installation

Once the mapper's stable 2.0 release is published:

```sh
composer require mathsgod/light-db-graphqlite-mappers:^2.0
```

The mapper requires the published `mathsgod/light-db:^2.0` package. No local
path repository or sibling checkout is needed for a stable installation.

## Register the mapper

Add the factory to your configured GraphQLite SchemaFactory before creating the
schema. The factory receives GraphQLite's FactoryContext automatically:

```php
use Light\Db\GraphQLite\Mappers\TypeMapperFactory;

$schemaFactory->addTypeMapperFactory(new TypeMapperFactory());
```

Then expose a Query and describe its Model subtype in the PHPDoc. The Model must
already be registered as a GraphQL output type in your application:

```php
use App\Model\User;
use Light\Db\Query;
use TheCodingMachine\GraphQLite\Annotations\Query as GraphQLQuery;

/** @return User[] */
#[GraphQLQuery]
public function users(): Query
{
    return User::Query()->sort('id:asc');
}
```

An inline comment such as `Query /* <User> */` is not a replacement for the
PHPDoc subtype. The mapper needs the subtype to build the list's output type.

## GraphQL output

For a GraphQL subtype named `User`, the generated wrapper is `DB_QUERY_User`:

```graphql
query {
    users {
        data(limit: 10, offset: 0) {
            id
            name
        }
        meta {
            total
            key
            name
            class
        }
    }
}
```

| Field | Type | Meaning |
| --- | --- | --- |
| `data` | `[T!]!` | Model results, with optional `limit` and `offset` arguments |
| `meta` | `DB_META!` | Query metadata |
| `meta.total` | `Int` | Filtered count before pagination |
| `meta.key` | `String` | Single key name, or comma-separated composite-key names |
| `meta.name` | `String` | Short PHP Model class name |
| `meta.class` | `String` | Fully qualified PHP Model class name |

Pagination retains the returned immutable Query. Multiple aliases can request
independent pages without mutating their shared root Query. Offset requires an
explicit limit, and negative limits/offsets are rejected. Omitting both arguments
uses the pagination already present on the root Query; an unbounded root has no
implicit page-size cap.

This package maps Query output. It does not implement authentication,
authorization, Model input types or database schema management. Configure access
controls and root Query filters in the application.

## Requirements

- PHP 8.3+
- `thecodingmachine/graphqlite` ^8.0
- `mathsgod/light-db` ^2.0

## Pagination errors

Negative `limit` or `offset` values return a client-safe GraphQL field error with
`extensions.code: "INVALID_PAGINATION"` and the invalid argument in the message.
An offset without a limit also returns a readable error. These messages remain
available with production error formatting; debug flags are not required.

## Tests and CI

```sh
composer install
composer test
```

The tests require `pdo_sqlite` and use a fresh in-memory database for each case.
They exercise real GraphQL execution, pagination aliases, filtered totals, empty
results, composite keys, missing subtypes, name-based type discovery and
production-formatted pagination errors. Missing SQLite support fails the suite;
tests are not silently skipped.

GitHub Actions runs PHP 8.3, 8.4 and 8.5 against GraphQLite **8.0.0** and the
latest compatible **8.x** release. The minimum-version jobs pin GraphQLite itself,
while allowing compatible dependency updates.

## License

MIT
