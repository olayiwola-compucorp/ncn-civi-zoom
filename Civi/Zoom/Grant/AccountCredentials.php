<?php

namespace Civi\Zoom\Grant;

use League\OAuth2\Client\Grant\AbstractGrant;

/**
 * Represents a client credentials grant.
 *
 * @link http://tools.ietf.org/html/rfc6749#section-1.3.4 Client Credentials (RFC 6749, §1.3.4)
 */
class AccountCredentials extends AbstractGrant {

  /**
   * @inheritdoc
   */
  protected function getName() {
    return 'account_credentials';
  }

  /**
   * @inheritdoc
   */
  protected function getRequiredRequestParameters()
  {
    return [];
  }

}
