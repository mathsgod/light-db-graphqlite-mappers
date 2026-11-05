<?php

declare(strict_types=1);
namespace Light\Db\GraphQLite\Mappers\Tests;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\{ObjectType, Type};
use GraphQL\Type\Schema;
use Light\Db\{Database, Query};
use Light\Db\GraphQLite\Mappers\{MissingParameterException, TypeMapper, TypeMapperFactory};
use Light\Db\GraphQLite\Mappers\Tests\Fixtures\MapperUser;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use TheCodingMachine\GraphQLite\{FactoryContext, SchemaFactory};
use TheCodingMachine\GraphQLite\Mappers\{TypeMapperFactoryInterface, TypeMapperInterface};

final class TypeMapperTest extends TestCase
{
    private Schema $schema;
    private FactoryContext $context;
    private TypeMapper $mapper;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE mapper_users(id INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL)');
        $pdo->exec("INSERT INTO mapper_users VALUES(1,'Ada',30),(2,'Bob',20),(3,'Cara',10)");
        $pdo->exec('CREATE TABLE mapper_memberships(user_id INTEGER, group_id INTEGER, PRIMARY KEY(user_id,group_id))');
        $pdo->exec('INSERT INTO mapper_memberships VALUES(1,10),(1,20)');
        Database::setDefault(new Database($pdo));
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed { return new $id(); }
            public function has(string $id): bool { return class_exists($id); }
        };
        $factory = new class implements TypeMapperFactoryInterface {
            public FactoryContext $context;
            public TypeMapper $mapper;
            public function create(FactoryContext $context): TypeMapperInterface
            {
                $this->context = $context;
                $mapper = (new TypeMapperFactory())->create($context);
                if (!$mapper instanceof TypeMapper) throw new \LogicException('Unexpected mapper');
                return $this->mapper = $mapper;
            }
        };
        $schemaFactory = new SchemaFactory(new Psr16Cache(new ArrayAdapter()), $container);
        $schemaFactory->addNamespace('Light\\Db\\GraphQLite\\Mappers\\Tests\\Fixtures');
        $schemaFactory->addTypeMapperFactory($factory);
        $this->schema = $schemaFactory->createSchema();
        $this->mapper = $factory->mapper;
        $this->context = $factory->context;
    }

    protected function tearDown(): void
    {
        Database::setDefault(null);
        parent::tearDown();
    }

    private function execute(string $query): array
    {
        // Production formatting: do not expose private exceptions with debug flags.
        return GraphQL::executeQuery($this->schema, $query)->toArray();
    }

    public function testPaginationAliasesAndFilteredTotalAreIndependent(): void
    {
        $result = $this->execute('{ adults { first: data(limit:1) { id name } second: data(limit:1,offset:1) { id } meta { total key name class } } }');
        self::assertArrayNotHasKey('errors', $result, json_encode($result));
        $page = $result['data']['adults'];
        self::assertSame([['id'=>1,'name'=>'Ada']], $page['first']);
        self::assertSame([['id'=>2]], $page['second']);
        self::assertSame(['total'=>2,'key'=>'id','name'=>'MapperUser','class'=>MapperUser::class], $page['meta']);
    }

    public function testMetaBeforeDataDoesNotDependOnFieldOrder(): void
    {
        $result = $this->execute('{ users { meta { total } data(limit:1,offset:2) { id } } }');
        self::assertArrayNotHasKey('errors', $result, json_encode($result));
        self::assertSame(3,$result['data']['users']['meta']['total']);
        self::assertSame([['id'=>3]],$result['data']['users']['data']);
    }

    public function testEmptyResultsAndZeroLimitRemainValid(): void
    {
        $result = $this->execute('{ emptyUsers { data(limit:20) { id } meta { total } } users { data(limit:0) { id } meta { total } } }');
        self::assertArrayNotHasKey('errors', $result, json_encode($result));
        self::assertSame(['data'=>[],'meta'=>['total'=>0]], $result['data']['emptyUsers']);
        self::assertSame(['data'=>[],'meta'=>['total'=>3]], $result['data']['users']);
    }

    public function testCompositeKeyMetadataKeepsItsStringShape(): void
    {
        $result = $this->execute('{ memberships { data(limit:1,offset:1) { userId groupId } meta { total key } } }');
        self::assertArrayNotHasKey('errors', $result, json_encode($result));
        self::assertSame([['userId'=>1,'groupId'=>20]], $result['data']['memberships']['data']);
        self::assertSame(['total'=>2,'key'=>'user_id,group_id'], $result['data']['memberships']['meta']);
    }

    public function testMissingSubtypeProducesAnActionableMappingException(): void
    {
        try {
            $this->mapper->mapClassToType(Query::class, null);
            self::fail('Expected missing subtype');
        } catch (MissingParameterException $error) {
            self::assertTrue($error->isClientSafe());
            self::assertStringContainsString('@return', $error->getMessage());
        }
    }

    public function testFreshMapperCanResolveWrapperAndMetaByName(): void
    {
        $mapper = new TypeMapper($this->context);
        self::assertTrue($mapper->canMapNameToType('DB_QUERY_MapperUser'));
        $type = $mapper->mapNameToType('DB_QUERY_MapperUser');
        self::assertSame('DB_QUERY_MapperUser',$type->name);
        self::assertInstanceOf(ObjectType::class,$type);
        $type->freeze();
        self::assertArrayHasKey('data',$type->getFields());
        self::assertSame('DB_META',$mapper->mapNameToType('DB_META')->name);
    }

    public function testSchemaIntrospectionResolvesTheWrapperByName(): void
    {
        $result = $this->execute('{ __type(name:"DB_QUERY_MapperUser") { name fields { name } } }');
        self::assertArrayNotHasKey('errors',$result,json_encode($result));
        self::assertSame('DB_QUERY_MapperUser',$result['data']['__type']['name']);
        self::assertSame(['data','meta'],array_column($result['data']['__type']['fields'],'name'));
    }

    public function testNonNullSubtypeKeepsAValidWrapperName(): void
    {
        $type = $this->mapper->mapClassToType(Query::class, Type::nonNull($this->schema->getType('MapperUser')));
        self::assertSame('DB_QUERY_MapperUser',$type->name);
    }

    public static function invalidPagination(): iterable
    {
        yield 'negative limit' => ['limit:-1', '"limit"', 'INVALID_PAGINATION'];
        yield 'negative offset' => ['limit:1,offset:-1', '"offset"', 'INVALID_PAGINATION'];
        yield 'offset without limit' => ['offset:1', 'limit', null];
    }

    #[DataProvider('invalidPagination')]
    public function testInvalidPaginationIsVisibleWithoutDebugFormatting(string $arguments, string $message, ?string $code): void
    {
        $result = $this->execute('{ users { data('.$arguments.') { id } } }');
        self::assertArrayHasKey('errors',$result);
        $error = $result['errors'][0];
        self::assertStringContainsString($message,$error['message']);
        self::assertNotSame('Internal server error',$error['message']);
        self::assertSame(['users','data'],$error['path']);
        if ($code !== null) self::assertSame($code,$error['extensions']['code']);
    }
}
