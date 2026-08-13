<?php

namespace Database\Seeders\Concerns;

use InvalidArgumentException;

trait NamesDepartments
{
    /**
     * Teams are departments, named from one list so the seeded app reads like a company.
     *
     * @return array<int, string>
     */
    protected function departments(): array
    {
        return [
            'Account Management',
            'Client Services',
            'Delivery',
            'Design',
            'Engineering',
            'Operations',
            'Quality Assurance',
            'Support',
        ];
    }

    protected function department(string $name): string
    {
        if (! in_array($name, $this->departments(), true)) {
            throw new InvalidArgumentException(sprintf(
                '[%s] is not a department. Add it to %s::departments() first.',
                $name,
                static::class,
            ));
        }

        return $name;
    }
}
