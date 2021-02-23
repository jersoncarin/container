<?php

/**
 * This file is part of the Bulk CMS.
 *
 * (c) Jerson Carin <jersoncarin25@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bulk\Components\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    
}