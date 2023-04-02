<?php

namespace Civi\Zoom\Grant;

use League\OAuth2\Client\Grant\GrantFactory;
use League\OAuth2\Client\Grant\Exception\InvalidGrantException;

/**
 * Represents a factory used when retrieving an authorization grant type.
 */
class ZoomGrantFactory extends GrantFactory
{

    /**
     * Registers a default grant singleton by name.
     *
     * @param  string $name
     * @return self
     */
    protected function registerDefaultGrant($name)
    {
        // PascalCase the grant. E.g: 'authorization_code' becomes 'AuthorizationCode'
        $class = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        $class = 'Civi\\Zoom\\Grant\\' . $class;

        $this->checkGrant($class);

        return $this->setGrant($name, new $class);
    }

}
