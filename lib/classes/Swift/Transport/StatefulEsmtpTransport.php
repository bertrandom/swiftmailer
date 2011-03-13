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
class Swift_Transport_StatefulEsmtpTransport implements Swift_StatefulTransport
{

  protected $_status;

  protected $_message;

  protected $_failedRecipients = array ();

  protected $_exception;

  protected $_sent = 0;
  
  protected $_started = false;
  
  protected $_bufferStarted = false;
  
  /**
   * Read / Write Buffer
   * @var Swift_Transport_EventBuffer
   */
  protected $_buffer;

  /* Callbacks */
  protected $_onStateChangeCallback = false;

  protected $_onStateChangePayload = false;

  protected $_onStateChangeMask = false;

  /* Mail Transactions */
  protected $_mailTransactionReversePath = false;

  protected $_mailTransactionTo = false;

  protected $_mailTransactionBcc = false;

  protected $_mailTransactionToDone = false;

  protected $_mailTransactionBccDone = false;
  
  protected $_mailTransactionStatus = -1;

  /* Constants */
  const MT_STATUS_ERROR = -20;
  const MT_STATUS_STOPPED = - 1;
  const MT_STATUS_READ_GREETING = 0;
  const MT_STATUS_WRITE_EHLO = 1;
  const MT_STATUS_READ_CAPABILITIES = 2;
  const MT_STATUS_WRITE_HELO = 3;
  const MT_STATUS_READ_HELO_ACK = 4;
  const MT_STATUS_TRANSACTION_READY = 5;
  const MT_STATUS_WRITE_MAIL_FROM = 6;
  const MT_STATUS_READ_MAIL_FROM = 7;
  const MT_STATUS_WRITE_RCPT_TO = 8;
  const MT_STATUS_READ_RCPT_TO_ACK = 9;
  const MT_STATUS_WRITE_DATA = 10;
  const MT_STATUS_READ_DATA_ACK = 11;
  const MT_STATUS_WRITE_BODY = 12;
  const MT_STATUS_READ_BODY_ACK = 13;
  const MT_STATUS_WRITE_RESET = 14;
  const MT_STATUS_READ_RESET_ACK = 15;
  const MT_STATUS_WRITE_QUIT = 16;
  const MT_STATUS_QUIT_ACK = 17;

  public function __construct(Swift_Transport_EventBuffer $buf, 
      array $extensionHandlers, Swift_Events_EventDispatcher $dispatcher)
  {
    $this->_buffer = $buf;
    $this->setExtensionHandlers($extensionHandlers);
    $this->_eventDispatcher = $dispatcher;
    $this->_setStatus(Swift_StatefulTransport::STATUS_NEW);
  }
  /* (non-PHPdoc)
   * @see Swift_Transport::isStarted()
   */
  public function isStarted()
  {
    return $this->_started && $this->_bufferStarted;
  }

  /* (non-PHPdoc)
   * @see Swift_Transport::start()
   */
  public function start()
  {
    if (!$this->_mailTransactionStatus<self::MT_STATUS_READ_GREETING)
    {
      if ($evt = $this->_eventDispatcher->createTransportChangeEvent($this))
      {
        $this->_eventDispatcher->dispatchEvent($evt, 'beforeTransportStarted');
        if ($evt->bubbleCancelled())
        {
          return;
        }
      }
      
      try
      {
        $this->_buffer->initialize($this->_getBufferParams());
        $this->_bufferStarted=true;
      }
      catch (Swift_TransportException $e)
      {
        $this->_throwException($e);
      }
      $this->_mailTransactionStatus=self::MT_STATUS_READ_GREETING;
    }
  }

  /* (non-PHPdoc)
   * @see Swift_Transport::stop()
   */
  public function stop()
  {
    if (!$this->_bufferStarted)
    {
      // Should not change a thing, but, just in case
      $this->_started=false;
      return;
    }
    $mustQuit = false;
    if ($evt = $this->_eventDispatcher->createTransportChangeEvent($this))
    {
      $this->_eventDispatcher->dispatchEvent($evt, 'beforeTransportStopped');
      if ($evt->bubbleCancelled())
      {
        return;
      }
    }
    if (!$this->_started)
    {
      if ($this->_mailTransactionStatus==self::MT_STATUS_READ_GREETING)
      {
        $mustQuit = true;
      }
    } 
    else 
    {
      $mustQuit = true;
    } 
    
    if ($mustQuit)
    {
        $this->_writeQuit();
    }
    else 
    {
      try
      {
        $this->_buffer->terminate();
        if ($evt)
        {
          $this->_eventDispatcher->dispatchEvent($evt, 'transportStopped');
        }
      }
      catch (Swift_TransportException $e)
      {
        $this->_throwException($e);
      }
    }
    $this->_started = false;
  }

  /**
   * Register a plugin.
   * 
   * @param Swift_Events_EventListener $plugin
   */
  public function registerPlugin(Swift_Events_EventListener $plugin)
  {
    $this->_eventDispatcher->bindEventListener($plugin);
  }
  
  /* (non-PHPdoc)
   * @see Swift_Transport_AbstractSmtpTransport::send()
   */
  public function send(Swift_Mime_Message $message, $failedRecipients = null)
  {
    // Check if the transport is in a valid state
    $this->_checkStatus(
        array (Swift_StatefulTransport::STATUS_NEW, 
            Swift_StatefulTransport::STATUS_STARTED ));
    $this->_status = Swift_StatefulTransport::STATUS_WAIT;
    $this->_exception = null;
    $this->_failedRecipients = array ();
    $this->_sent = 0;
    $this->_message = $message;
    return $this;
  }

  public function startSend()
  {
    /* Prepare Mail Transactions */
    if (! $reversePath = $this->_getReversePath($this->_message))
    {
      throw new Swift_TransportException(
          'Cannot send message without a sender address');
    }
    
    if (false !=
     ($this->_mailTransactionEvt = $this->_eventDispatcher->createSendEvent(
        $this, $this->_message)))
    {
      $this->_eventDispatcher->dispatchEvent($this->_mailTransactionEvt, 
          'beforeSendPerformed');
      if ($this->_mailTransactionEvt->bubbleCancelled())
      {
        return 0;
      }
    }
    
    $this->_mailTransactionReversePath = $this->_getReversePath(
        $this->_message);
    $this->_mailTransactionTo = ( array ) $this->_message->getTo();
    // Merge cc with To to send a singe mail for all of them
    $cc = ( array ) $this->_message->getCc();
    foreach ( $cc as $forwardPath => $name )
    {
      if (! isset($this->_mailTransactionTo[$forwardPath]))
      {
        $this->_mailTransactionTo[$forwardPath] = $name;
      }
    }
    $this->_mailTransactionBcc = ( array ) $this->_message->getBcc();
    
    $this->_message->setBcc(array ());
    
    $this->_mailTransactionToDone = false;
    $this->_mailTransactionBccDone = false;
    
    $this->work();
    
    return true;
  }

  public function work()
  {
    switch ($this->_mailTransactionStatus)
    {
      case self::MT_STATUS_START :
        if (! $this->_started)
        {
          if (false !=
           ($evt = $this->_eventDispatcher->createTransportChangeEvent(
              $this)))
          {
            $this->_eventDispatcher->dispatchEvent($evt, 
                'beforeTransportStarted');
            if ($evt->bubbleCancelled())
            {
              return;
            }
          }
          
          try
          {
            $this->_buffer->initialize($this->_getBufferParams());
          } catch ( Swift_TransportException $e )
          {
            $this->_exception = $e;
            $this->_status = Swift_StatefulTransport::STATUS_ERROR;
          }
        }
        return;
      case self::MT_STATUS_READ_GREETING :
        $this->_readGreeting();
        break;
        
      case self::MT_STATUS_WRITE_EHLO :
        $this->_writeHeloCommand();
        break;
        
      case self::MT_STATUS_READ_CAPABILITIES :
        $this->_readCapabilities();
        break;
        
      case self::MT_STATUS_WRITE_HELO :
        $this->_writeHeloCommand();
        break;
        
      case self::MT_STATUS_READ_HELO_ACK :
        $this->_readHeloReply();
        break;
        
      case self::MT_STATUS_TRANSACTION_READY :
        // Wait for a transaction to start
        break;
        
      case self::MT_STATUS_WRITE_MAIL_FROM :
        $this->_writeMailFrom();
        break;
        
      case self::MT_STATUS_READ_MAIL_FROM :
        $this->_readMailFromReply();
        break;
        
      case self::MT_STATUS_WRITE_RCPT_TO :
        $this->_writeRcptTo();
        break;
        
      case self::MT_STATUS_READ_RCPT_TO_ACK :
        $this->_readRcptToReply();
        break;
        
      case self::MT_STATUS_WRITE_DATA :
        $this->_writeDataCommand();
        break;
        
      case self::MT_STATUS_READ_DATA_ACK :
        $this->_readDataReply();
        break;
        
      case self::MT_STATUS_WRITE_BODY :
        $this->_writeBody();
        break;
        
      case self::MT_STATUS_READ_BODY_ACK :
        $this->_writeBodyAck();
        break;
        
      case self::MT_STATUS_WRITE_RESET :
        $this->_writeResetCommand();
        break;
        
      case self::MT_STATUS_READ_RESET_ACK :
        $this->_readResetReply();
        break;
        
      case self::MT_STATUS_WRITE_QUIT :
        $this->_writeQuit();
        break;
        
      case self::MT_STATUS_QUIT_ACK :
        $this->_readQuitReply();
        break;
    }
  }

  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::getFailedRecipients()
   */
  public function getFailedRecipients()
  {
    return $this->_failedRecipients;
  }

  /*(non-PHPdoc)
   * @see Swift_StatefulTransport::getSentCount()
   */
  public function getSentCount()
  {
    return $this->_sent;
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
        array (Swift_StatefulTransport::STATUS_DONE, 
            Swift_StatefulTransport::STATUS_ERROR ));
    $this->_status = Swift_StatefulTransport::STATUS_STARTED;
    /* 
     * Restore Message in original status before 
     * Freeing It, if used outside
     */
    $this->_message->setBcc($this->_mailTransactionBcc);
    if ($this->_mailTransactionEvt)
    {
      if ($this->_sent ==
           count($this->_mailTransactionTo) +
           count($this->_mailTransactionBcc))
          {
            $this->_mailTransactionEvt->setResult(
                Swift_Events_SendEvent::RESULT_SUCCESS);
      } elseif ($this->_sent > 0)
      {
        $this->_mailTransactionEvt->setResult(
            Swift_Events_SendEvent::RESULT_TENTATIVE);
      } else
      {
        $this->_mailTransactionEvt->setResult(
            Swift_Events_SendEvent::RESULT_FAILED);
      }
      $this->_mailTransactionEvt->setFailedRecipients(
          $this->_failedRecipients);
      $this->_eventDispatcher->dispatchEvent($this->_mailTransactionEvt, 
          'sendPerformed');
    }
    
    $this->_message->generateId(); //Make sure a new Message ID is used
    $this->_message = null;
  }

  /* (non-PHPdoc)
   * @see Swift_StatefulTransport::attachBaseEvent()
   */
  public function attachBaseEvent($baseEvent)
  {
    // Attach Callbacks
    $this->_buffer->notifyOnReadReady(
        array ($this, 'readReadyCallback' ));
    $this->_buffer->notifyOnWriteLow(array ($this, 
        'writeReadyCallback' ));
    $this->_buffer->notifyOnError(array ($this, 
        'onBufferErrorCallback' ));
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
  public function notifyOnStateChange($callback, $payload = null, 
      array $statusMask = null)
  {
    if ($callback === false)
    {
      $this->_onStateChangeCallback = false;
      $this->_onStateChangeMask = false;
      $this->_onStateChangePayload = false;
    }
    if (! is_callable($callback))
    {
      throw new Swift_SwiftException(
          'The Given Callback is not callable');
    }
    $this->_onStateChangeCallback = $callback;
    $this->_onStateChangePayload = $payload;
    $this->_onStateChangeMask = $statusMask;
  
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
  public function readReadyCallback($eventRessource, $payload = null)
  {
    $this->work();
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
  public function writeReadyCallback($eventRessource, $payload = null)
  {
    $this->work();
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
  public function onBufferErrorCallback($eventRessource, $error, 
      $payload = null)
  {
    $read = 'Write Error : ';
    if ($error & EVBUFFER_READ)
    {
      $message = 'Read Error : ';
    }
    if ($error & EVBUFFER_EOF)
    {
      $message .= 'Connection Closed';
    } elseif ($error & EVBUFFER_TIMEOUT)
    {
      $message .= 'Timeout';
    } else
    {
      $message .= 'Unknown Error';
    }
    $this->_exception = new Swift_IoException($message);
    $this->_setStatus(Swift_StatefulTransport::STATUS_ERROR);
  }

  // Start Of Helpers
  

  protected function _setStatus($newStatus)
  {
    if ($this->_status != $newStatus)
    {
      $this->_status = $newStatus;
      $this->_notifyChange($newStatus);
    }
  }

  protected function _checkStatus(array $accepted)
  {
    foreach ( $accepted as $status )
    {
      if ($status != $this->_status)
      {
        throw new Swift_SwiftException(
            'The transport is in an invalid State Operation cancelled');
      }
    }
  }

  protected function _notifyChange($newStatus)
  {
    // TODO Implement
  }
}
    