<?php

declare(strict_types=1);
namespace Light\Db\GraphQLite\Mappers\Tests\Fixtures;

use Light\Db\Query;
use TheCodingMachine\GraphQLite\Annotations\Query as GraphQLQuery;

final class QueryController
{
    /** @return MapperUser[] */
    #[GraphQLQuery]
    public function users(): Query
    {
        return MapperUser::Query()->sort('id:asc');
    }

    /** @return MapperUser[] */
    #[GraphQLQuery]
    public function adults(): Query
    {
        return MapperUser::Query()->where(['age' => ['gte' => 18]])->sort('id:asc');
    }

    /** @return MapperUser[] */
    #[GraphQLQuery]
    public function emptyUsers(): Query
    {
        return MapperUser::Query(['id' => -1]);
    }

    /** @return Membership[] */
    #[GraphQLQuery]
    public function memberships(): Query
    {
        return Membership::Query()->sort('user_id:asc,group_id:asc');
    }
}
