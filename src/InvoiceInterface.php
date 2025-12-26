<?php

declare(strict_types=1);

namespace Drupal\e_invoice;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a invoice entity type.
 */
interface InvoiceInterface extends ContentEntityInterface, EntityOwnerInterface {

}
