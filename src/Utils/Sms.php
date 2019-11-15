<?php
namespace alexeevdv\React\Smpp\Utils;

use alexeevdv\React\Smpp\Pdu\TLV;
use alexeevdv\React\Smpp\Pdu\SubmitSm;
use alexeevdv\React\Smpp\Proto\Contract\DataCoding;

class Sms extends SubmitSm
{

    /**
	 * Use sarMsgRefNum and sar_total_segments with 16 bit tags
	 * @var int
	 */
	const CSMS_16BIT_TAGS = 0;
	
	/**
	 * Use message payload for CSMS
	 * @var int
	 */
	const CSMS_PAYLOAD = 1;
	
	/**
	 * Embed a UDH in the message with 8-bit reference.
	 * @var int
	 */
    const CSMS_8BIT_UDH = 2;
    /**
     * 
     *
     * @var int
     */
    private $csmsType = Sms::CSMS_16BIT_TAGS;
    /**
     * 
     *
     * @var int
     */
    private $sarMsgRefNum;
    /**
     *
     * @var array
     */
    private $tlv = [];

    public function __construct($body = '')
    {
        parent::__construct($body);
    }
    
    public function setCSmsType(int $type)
    {
        $this->csmsType = $type;
        return $this;
    }

    public function getCSmsType()
    {
        return $this->csmsType;
    }

    public function addTLV(TLV $tlv)
    {
        $this->tlv[] = $tlv;
        return $this;
    }

    public function setTLV(TLV $tlv)
    {
        $this->tlv = $tlv;
        return $this;
    }

    public function getTLV()
    {
        return $this->tlv;
    }
    /**
     * 
     *
     * @param String $message
     * @param [type] $split
     * @param [type] $dataCoding
     * @return String
     */
    public function splitMessageString($message, $split, $dataCoding = DataCoding::DEFAULT)
    {
        switch ($dataCoding) {
            case DataCoding::DEFAULT:
                $msgLength = strlen($message);
                // Do we need to do php based split?
                $numParts = floor($msgLength / $split);
                if ($msgLength % $split == 0) {
                    $numParts--;
                }
                $slowSplit = false;
                for ($i = 1; $i <= $numParts; $i++) {
                    if ($message[$i * $split - 1] == "\x1B") {
                        $slowSplit = true;
                        break;
                    }
                }
                if (!$slowSplit) {
                    return str_split($message, $split);
                }

                // Split the message char-by-char
                $parts = array();
                $part  = null;
                $n     = 0;
                for ($i = 0; $i < $msgLength; $i++) {
                    $logger = $message[$i];
                    // reset on $split or if last char is a GSM 03.38 escape char
                    if ($n == $split || ($n == ($split - 1) && $logger == "\x1B")) {
                        $parts[] = $part;
                        $n       = 0;
                        $part    = null;
                    }
                    $part .= $logger;
                }
                $parts[] = $part;
                return $parts;
            case DataCoding::UCS2: // UCS2-BE can just use str_split since we send 132 octets per message, which gives a fine split using UCS2
            default:
                return str_split($message, $split);
        }
    }

    public function getCsmsReference()
    {
        $limit = ($this->csmsType == self::CSMS_8BIT_UDH) ? 255 : 65535;
        // if (!isset($sarMsgRefNum)) {
        $sarMsgRefNum = mt_rand(0, $limit);
        // }

        $sarMsgRefNum++;
        if ($sarMsgRefNum > $limit) {
            $sarMsgRefNum = 0;
        }

        return $sarMsgRefNum;
    }

    public function parse(){
        $message = $this->getShortMessage();
        $dataCoding = $this->getDataCoding();
        $msgLength = strlen($message);
        if ($msgLength > 160 && $dataCoding != DataCoding::UCS2 && $dataCoding != DataCoding::DEFAULT) {
            return false;
        }

        switch ($dataCoding) {
            case DataCoding::UCS2:
                $singleSmsOctetLimit = 140; // in octets, 70 UCS-2 chars
                $csmsSplit           = 132; // There are 133 octets available, but this would split the UCS the middle so use 132 instead
                break;
            case DataCoding::DEFAULT: /*$dataCoding = DataCoding::DEFAULT*/
                $singleSmsOctetLimit = 160; // we send data in octets, but GSM 03.38 will be packed in septets (7-bit) by SMSC.
                $csmsSplit           = 152; // send 152 chars in each SMS since, we will use 16-bit CSMS ids (SMSC will format data)
                break;
            default:
                $singleSmsOctetLimit = 254; // From SMPP standard
                break;
        }
        $doCsms = false;
        if ($msgLength > $singleSmsOctetLimit) {
            if ($this->csmsType != self::CSMS_PAYLOAD) {
                $doCsms        = true;
                $parts         = $this->splitMessageString($message, $csmsSplit, $dataCoding);
                $short_message = reset($parts);
                $csmsReference = $this->getCsmsReference();
            }
        } else {
            $short_message = $message;
            $doCsms        = false;
        }
        \var_dump($doCsms,$parts,$csmsReference);exit;
    
    // Deal with CSMS
        if ($doCsms) {
            if ($this->csmsType == self::CSMS_PAYLOAD) {
                $payload = new TLV(TLV::MESSAGE_PAYLOAD, $message, $msgLength);
                // return submitSm($from, $to, null, (empty($tags) ? array($payload) : array_merge($tags, $payload)), $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod);
                return '';
            } else if ($this->csmsType == self::CSMS_8BIT_UDH) {
                $seqnum = 1;
                foreach ($parts as $part) {
                    $udh = pack('cccccc', 5, 0, 3, substr($csmsReference, 1, 1), count($parts), $seqnum);
                    // $res = submitSm($from, $to, $udh . $part, $tags, $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod, (SmppClient::$sms_esm_class | 0x40));
                    $seqnum++;
                }
                return $res;
            } else {
                var_dump('okok');
                $sarMsgRefNum    = new TLV(TLV::SAR_MSG_REF_NUM, $csmsReference, 2, 'n');
                $sar_total_segments = new TLV(TLV::SAR_TOTAL_SEGMENTS, count($parts), 1, 'c');
                $seqnum             = 1;
                foreach ($parts as $part) {
                    $sartags = array($sarMsgRefNum, $sar_total_segments, new TLV(TLV::SAR_SEGMENT_SEQNUM, $seqnum, 1, 'c'));
                    // $res     = submitSm($from, $to, $part, $seqnum, (empty($tags) ? $sartags : array_merge($tags, $sartags)), $dataCoding, $priority, $scheduleDeliveryTime, $validityPeriod);
                    $seqnum++;
                }
                return $res;
            }
        }
        return parent::__toString();
    }

    // public function submitSm(){

    // }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->parse();
    }
}
