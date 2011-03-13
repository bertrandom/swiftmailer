<?php
/**
 * This File is part of SwiftMailer.
 * (c) 2011 Xavier De Cock
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Swift_EventMailer Class, a libevent version of Swift_Mailer
 * 
 * This change massively the way Swift works, and should be used with precautions.
 * Please understand how it works before using it.
 * 
 * Due to the massively parallel way it can work, it does NOT implement batchSend
 * 
 * If you need batchSend, implement It by a wrapper around
 * 
 * Events must be attached to either the transports or the messages
 * 
 * @author Xavier De Cock <xdecock@gmail.com>
 * @package Swift
 *
 */
Class Swift_EventMailer 
{
  
  /**
   * Constructor, sanity check + Basic Setup
   * @throws Swift_SwiftException
   */
  public function __construct() 
  {
    if (!extension_loaded('libevent')) 
    {
      throw new Swift_SwiftException('libevent extension not found, please install and configure before continuing');
    }
  }
  
  /**
   * Returns the number of currently transports in an active state
   * @return int
   */
  public function activeTransports()
  {
    
  }
  
  /**
   * Adds a message to the MailerQueue
   * @param Swift_Message $message
   * @param Swift_Transport $transport
   * @param array $callbacks
   * @param mixed $payload
   */
  public function send(Swift_Message $message, Swift_StateFullTransport $transport, array $callbacks=null, $payload=null)
  {
    
  }
  
  /**
   * Executes a work loop, or hold until there is no work to do
   * @param boolean $block set this to true if you want to block 
   * 	until everytransport finish his transaction
   * @param boolean $exitLoopOnComplete Set this to true if you want 
   * 	to exit blocking loop as soon as a transport becomes free
   * 	again
   */
  public function workLoop($block=false, $exitLoopOnComplete=false)
  {
    
  }
  
  /**
   * Starts a Swift_StatefulTransport with the workloop
   * The callbacks will receive the stateFull transports on error 
   * 
   * @param Swift_StatefulTransport $transport
   * @param callback $onTransportReadyCallback
   * @param callback $onErrorCallback
   */
  public function startTransport(Swift_StatefulTransport $transport, $onTransportReadyCallback=null, $onErrorCallback=null)
  {
    
  }

  /**
   * Stops a Swift_StatefulTransport with the workloop
   * The callbacks will receive the StateFulTransports on error 
   * 
   * @param Swift_StatefulTransport $transport
   * @param callback $onTransportStoppedCallback
   * @param callback $onErrorCallback
   */
  public function stopTransport(Swift_StatefulTransport $transport, $onTransportStoppedCallback=null, $onErrorCallback=null)
  {
    
  }  
}