<?php

namespace Civi\Zoom\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Civi\Zoom\Grant\ZoomGrantFactory;

class Zoom extends GenericProvider {
  use BearerAuthorizationTrait;

  public function __construct(array $options = [], array $collaborators = []) {
    parent::__construct($options, $collaborators);

    $factory = new ZoomGrantFactory();
    $this->setGrantFactory($factory);
  }

}
