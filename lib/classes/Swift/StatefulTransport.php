<?php
/*
 * This File is part of SwiftMailer.
 * (c) 2011 Xavier De Cock
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Stateful version of transports
 * The goal of those transports are to let php do other things as soon as @author xavier
 * blocking event is encountered
 * 
 * @author Xavier De Cock <xdecock@gmail.com>
 */
Interface Swift_StatefulTransport extends Swift_Transport
{
  
}