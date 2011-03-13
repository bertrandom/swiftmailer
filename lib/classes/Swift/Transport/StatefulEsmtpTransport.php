<?php
/*
 * This File is part of SwiftMailer.
 * (c) 2011 Xavier De Cock
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Statefull ESMTP Transport Implementation, this is needed for any form of
 * Event Based Handling
 * 
 * @author xavier
 */
Class Swift_Transport_StatefulEsmtpTransport 
  extends Swift_Transport_EsmtpTransport
  implements Swift_StatefulTransport
{
  protected $_status;
  protected $_message;
  protected $_failedRecipients=array();
  protected $_exception;
  
  protected $_onStateChangeCallback=false;
  protected $_onStateChangePayload=false;
  protected $_onStateChangeMask=false;
  
  public function __construct(Swift_Transport_EventBuffer $buf, 
    array $extensionHandlers, 
    Swift_Events_EventDispatcher $dispatcher) 
  {
    $this->_buffer=$buf;
    $this->setExtensionHandlers($extensionHandlers);
    $this->_eventDispatcher=$dispatcher;
    $this->_setStatus(Swift_StatefulTransport::STATUS_NEW);
  }

  /* (non-PHPdoc)
   * @see Swift_Transport_AbstractSmtpTransport::send()
   */
  public function send(Swift_Mime_Message $message, $failedRecipients = null) 
  {
    // Check if the transport is in a valid state
    $this->_checkStatus(
      array(Swift_StatefulTransport::STATUS_NEW, 
        Swift_StatefulTransport::STATUS_STARTED)
      );
    $this->_status=Swift_StatefulTransport::STATUS_WAIT;
    $this->_exception=null;
    $this->_failedRecipients=array();
    $this->_message=$message;
    return $this;    	
  }

  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::getFailedRecipients()
   */
  public function getFailedRecipients() 
  {
    return $this->_failedRecipients;
  }

  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::getTransportStatus()
   */
  public function getTransportStatus() 
  {
    return $this->_status;
  }
  
  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::getCurrentMessage()
   */
  public function getCurrentMessage() 
  {
    return $this->_message;
  }
  
  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::freeMessage()
   */
  public function freeMessage() 
  {
    $this->_checkStatus(
      array(Swift_StatefulTransport::STATUS_DONE, Swift_StatefulTransport::STATUS_ERROR)
      );
    $this->_status=Swift_StatefulTransport::STATUS_STARTED;
    $this->_message=null;
  }
  
  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::attachBaseEvent()
   */
  public function attachBaseEvent($baseEvent) 
  {
    // Attach Callbacks
    $this->_buffer->notifyOnReadReady(array($this,'readReadyCallback'));
    $this->_buffer->notifyOnWriteDone(array($this,'writeReadyCallback'));
    $this->_buffer->notifyOnError(array($this,'onBufferErrorCallback'));
    // Attach Event
    $this->_buffer->attachBaseEvent($baseEvent);
  }
  
  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::getException()
   */
  public function getException() 
  {
  	return $this->_exception;
  }
  
  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::notifyOnStateChange()
   */
  public function notifyOnStateChange($callback, $payload = null, array $statusMask = null) 
  {
    if ($callback===false) 
    {
      $this->_onStateChangeCallback=false;
      $this->_onStateChangeMask=false;
      $this->_onStateChangePayload=false;
    }
    if (!is_callable($callback)) 
    {
      throw new Swift_SwiftException('The Given Callback is not callable');
    }
    $this->_onStateChangeCallback=$callback;
    $this->_onStateChangePayload=$payload;
    $this->_onStateChangeMask=$statusMask;
  	
  }
  
  // Callbacks for buffer DO NOT USE THEM
  
  /**
   * Method Called When Read datas are ready
   * 
   * @see libevent
   * @internal
   * @access private
   * @param ressource $eventRessource
   * @param mixed $payload
   */
  public function readReadyCallback($eventRessource, $payload=null) {
    
  }
  
  /**
   * Method Called When write buffer is below low-watermark
   * 
   * The low Watermark will be adapted with the sending sequence Swift is in
   * 
   * (Higher the "Low-watermark" on Data sequence)
   * 
   * @see libevent
   * @internal
   * @param ressource $eventRessource
   * @param mixed $payload
   */
  public function writeReadyCallback($eventRessource, $payload=null) {
    
  }
  
  /**
   * Method Called When an Error Occurs on the file descriptor
   * 
   * Warning, never call this method yourself, it will eat your bytes.
   *
   * @see libevent
   * @internal
   * @param ressource $eventressource
   * @param short $error
   * @param mixed $payload
   * @version 0.04
   */
  public function onBufferErrorCallback($eventRessource, $error, $payload=null) {
    $read='Write Error : ';
    if ($error & EVBUFFER_READ) 
    {
      $message='Read Error : ';
    } 
    if ($error & EVBUFFER_EOF)
    {
      $message.='Connection Closed';
    }
    elseif ($error & EVBUFFER_TIMEOUT)
    {
      $message.='Timeout';
    }
    else
    {
      $message.='Unknown Error';
    }
    $this->_exception=new Swift_IoException($message);
    $this->_setStatus(Swift_StatefulTransport::STATUS_ERROR);
  }
  
  // Start Of Helpers

  protected function _setStatus($newStatus) 
  {
    if ($this->_status<>$newStatus) 
    {
      $this->_status=$newStatus;
      $this->_notifyChange($newStatus);
    }
  }
  
  protected function _checkStatus(array $accepted) 
  {
    foreach ($accepted as $status) {
      if ($status <> $this->_status) {
        throw new Swift_SwiftException('The transport is in an invalid State Operation cancelled');
      }
    }
  }
  
  protected function _notifyChange($newStatus) 
  {
    // TODO Implement
  }
}