<?php
/*
 * This File is part of SwiftMailer.
 * (c) 2011 Xavier De Cock
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A libevent aware version of StreamBuffer
 *
 * @author Xavier De Cock <xdecock@gmail.com>
 * @package Swift
 * @subpackage Transport
 */
Class Swift_Transport_EventStreamBuffer
  extends Swift_Transport_StreamBuffer
  implements Swift_Transport_EventBuffer
{
	/* (non-PHPdoc)
	 * @see Swift_Transport_EventBuffer::setEventBase()
	 */
	public function setEventBase($eventBaseRessource) {
		// TODO Auto-generated method stub
		
	}

	/* (non-PHPdoc)
	 * @see Swift_Transport_EventBuffer::NotifyOnReadReady()
	 */
	public function NotifyOnReadReady($callback) {
		// TODO Auto-generated method stub
		
	}

	/* (non-PHPdoc)
	 * @see Swift_Transport_EventBuffer::NotifyOnWriteDone()
	 */
	public function NotifyOnWriteDone($callback) {
		// TODO Auto-generated method stub
		
	}

  
}