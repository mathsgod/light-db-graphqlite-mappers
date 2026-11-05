# Migrating from mapper 1.x to 2.x

Mapper 2.x supports the light-entity-based light-db 2 Query API. Upgrade the ORM
and mapper together; mapper 1.x must not be used with immutable v2 Queries. This
guide describes the v2 implementation and does not announce a published version.

## 1. Update dependencies

| Component | Required version |
| --- | --- |
| PHP | 8.3+ |
| `mathsgod/light-db-graphqlite-mappers` | 2.x |
| `mathsgod/light-db` | ^2.0 |
| `thecodingmachine/graphqlite` | ^8.0 |
| `mathsgod/light-entity` | 1.x, installed through light-db |

After the stable mapper release is available, require its `^2.0` version in the
application. Remove old path repositories/version overrides that select mapper
1.x or legacy light-db. Development applications testing the unreleased mapper
must configure their own source repository and constraint; dependency repositories
are not inherited by the root application.

The mapper now declares light-db as a runtime dependency instead of a development
requirement. Its Composer configuration uses the published ORM; no sibling
light-db checkout or `@dev` flag is needed for stable installations.

The old mapper is preserved on `1.x`. Keep the old application lock file for a
coordinated rollback; reverting only the mapper while retaining light-db 2 is
not a supported downgrade.

## 2. Keep the mapper namespace and register its factory

The package namespace remains `Light\Db\GraphQLite\Mappers`. It does not move to
`Light\Framework`, even when upgrading Light framework at the same time.

```php
use Light\Db\GraphQLite\Mappers\TypeMapperFactory;

$schemaFactory->addTypeMapperFactory(new TypeMapperFactory());
```

Register before `createSchema()`. The factory obtains the current FactoryContext
from GraphQLite. Existing direct construction of TypeMapper with a valid context
is also possible, but application code does not need to construct that context
just to register the factory.

## 3. Declare the output subtype

The return type `Light\Db\Query` alone does not identify which GraphQL Model type
is inside the collection. Keep a PHPDoc list subtype on every exposed method:

```php
use App\Model\User;
use Light\Db\Query;
use TheCodingMachine\GraphQLite\Annotations\Query as GraphQLQuery;

/** @return User[] */
#[GraphQLQuery]
public function users(): Query
{
    return User::Query()->filters(['status' => 1])->sort('id:asc');
}
```

Register the User output type using the application's normal GraphQLite setup.
Replace illustrative inline comments such as `Query /* <User> */` with actual
PHPDoc. A missing subtype cannot be mapped.

## 4. Retain immutable Query return values

The v2 mapper keeps the Query returned by `limit()` and `offset()`. Apply the same
rule to application code before returning a Query:

```php
$query = User::Query();
$query = $query->filters($filters)->sort($sort);
return $query;
```

Calling `$query->limit(20)` without assignment does not change the root Query.
Custom resolvers or application wrappers that modify Query pagination must also
retain their return values.

Multiple aliases now request independent pages:

```graphql
query {
    users {
        first: data(limit: 10, offset: 0) { id }
        second: data(limit: 10, offset: 10) { id }
        meta { total }
    }
}
```

`meta.total` counts the filtered set before limit/offset. Do not calculate page
counts from a paginated data array. Offset requires an explicit limit, even if
the root Query already has one; negative values are rejected. An unbounded root
Query remains unbounded when neither argument is supplied, so enforce any desired
page-size policy in the application.

Negative pagination now produces client-safe field errors rather than a generic
`Internal server error`. Their `extensions.code` is `INVALID_PAGINATION`, with
`category: "db_query"`. Clients can display the message without enabling server
debug output. An offset without a limit also returns an actionable message.

## 5. Review metadata and schema caches

The wrapper shapes remain `data: [T!]!` and `meta: DB_META!`. Generated wrapper
names use the GraphQL subtype name, for example `DB_QUERY_User`. Metadata fields
remain `total`, `key`, `name` and `class` with their existing scalar types.

A single key still produces `meta.key: "user_id"`. Composite keys are serialized
as a comma-separated String, for example `"order_id,product_id"`; code that treats
this metadata as one column name must handle that case explicitly. It is not a
GraphQL list or a complete key value for a row.

`meta.class` reflects the actual PHP Model class. A separate framework namespace
migration can therefore change its value even when GraphQL type names and field
shapes stay the same. Compare your application's generated schema and persisted
class-name references rather than assuming the entire application is unchanged.

After deploying, invalidate cached schema/type-discovery mappings using the
application's cache policy and restart long-running workers. Do not discard
unrelated auth/session cache entries indiscriminately if caches are shared.

## 6. Validate the application

1. Install matching ORM/mapper versions and regenerate Composer autoload files.
2. Check introspection and both type-by-name and class-based schema discovery.
3. Exercise no-result queries, sorting/filtering, first and later pages, total
   counts, pagination aliases and any composite-key metadata consumers.
4. Exercise invalid pagination and application authentication/authorization;
   the mapper itself does not grant access or scope results to a user.
5. Run the actual frontend against staging, including initial page requests and
   API error responses. Preserve the previous code and lock file for rollback.

For ORM changes such as Adapter removal, filters, JSON values, Model hooks and
transactions, follow the
[light-db v2 migration guide](https://github.com/mathsgod/light-db/blob/v2.0.0/docs/migration-v2.md).

## Package regression suite

Run `composer test` with `pdo_sqlite` enabled. The suite uses isolated in-memory
databases and executes GraphQL queries through the registered mapper factory.
CI runs the suite on PHP 8.3/8.4/8.5 with GraphQLite 8.0.0 and the latest
compatible 8.x release. SQLite driver failures and skipped tests fail CI.
