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
 * @package Swift
 */
Interface Swift_StatefulTransport extends Swift_Transport
{
  const STATUS_NEW       = 0;
  const STATUS_STARTED   = 1;
  const STATUS_BUSY      = 2;
  const STATUS_WAIT      = 3;
  const STATUS_DONE      = 4;
  const STATUS_ERROR     = -1;
  
  /**
   * Returns a STATUS_* Constant
   * The goal is to provide a simple way to know if a transport is usable or not
   * 
   * @return int
   */
  public function getTransportStatus();
  
  /**
   * Returns the message being currently in the transport 
   * @return Swift_Message
   */
  public function getCurrentMessage();
  
  /**
   * Transition the Transport Status to STARTED 
   * By removing the internal message descriptor
   * @return Swift_StatefulTransport 
   */
  public function freeMessage();
  
  /**
   * Continue the ongoing work. This should be called when needed
   * ressources are available again.
   * 
   * @return Swift_StatefulTransport
   */
  public function attachBaseEvent($baseEvent);
  
  /**
   * Retrieves Failed Recipients
   * @return array
   */
  public function getFailedRecipients();
  
  /**
   * Return the Exception encountered by the transport
   * 
   * @return Swift_SwiftException
   */
  public function getException();
  
  /**
   * Sets the callbacks for notification when the state change
   * @param callback $callback
   */
  public function notifyOnStateChange($callback, $payload=null, $statusMask=null);
  
}