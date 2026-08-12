<?php

namespace Database\Seeders\Concerns;

use InvalidArgumentException;

trait NamesDepartments
{
    /**
     * Teams are departments — the groups of people inside an organization who do
     * the work. Naming them from one list keeps the seeded app reading like a real
     * company instead of a set of placeholders.
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

    /**
     * Guard against a department name drifting out of the list.
     *
     * Without this a typo silently becomes a new "department", which is exactly
     * the kind of thing that makes seeded data stop looking deliberate.
     */
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
