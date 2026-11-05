<?php

declare(strict_types=1);
namespace Light\Db\GraphQLite\Mappers\Tests\Fixtures;

use Light\Db\Model;
use TheCodingMachine\GraphQLite\Annotations\{Field, Type};

#[Type(name: 'MapperMembership')]
final class Membership extends Model
{
    protected static $_table = 'mapper_memberships';

    #[Field]
    public function getUserId(): int { return $this->user_id; }

    #[Field]
    public function getGroupId(): int { return $this->group_id; }
}
