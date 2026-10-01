<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Functional;

trait ContainerServiceTrait
{
    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
