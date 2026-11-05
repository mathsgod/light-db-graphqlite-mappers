<?php

declare(strict_types=1);
namespace Light\Db\GraphQLite\Mappers\Tests\Fixtures;

use Light\Db\Model;
use TheCodingMachine\GraphQLite\Annotations\{Field, Type};

#[Type(name: 'MapperUser')]
final class MapperUser extends Model
{
    protected static $_table = 'mapper_users';

    #[Field]
    public function getId(): int { return $this->id; }

    #[Field]
    public function getName(): string { return $this->name; }
}
