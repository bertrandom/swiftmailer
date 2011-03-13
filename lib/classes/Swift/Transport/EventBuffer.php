<?php
/*
 * This File is part of SwiftMailer.
 * (c) 2011 Xavier De Cock
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This libEvent Aware Buffer is only to be used with statefull
 * Transports, you are warned, it will probably eat your datas if 
 * you don't use libevent
 * 
 * @author Xavier De Cock <xavier@gmail.com>
 * @package Swift
 * @subpackage Transport
 */
interface Swift_Transport_EventBuffer
  extends Swift_Transport_IoBuffer
{
  public function attachBaseEvent($eventBaseRessource);
  
  public function notifyOnReadReady($callback);
  
  public function notifyOnWriteDone($callback);
 
  public function notifyOnError($callback);
  
  public function setWriteLowWatermark ($wm);
}