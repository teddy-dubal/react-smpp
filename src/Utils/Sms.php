<?php
namespace alexeevdv\React\Smpp\Utils;

use alexeevdv\React\Smpp\Pdu\SubmitSm;
use alexeevdv\React\Smpp\Proto\Contract\DataCoding;
use Exception;
use React\Socket\ConnectionInterface;

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
     *
     */
    private $loop;
    /**
     *
     *
     * @var ConnectionInterface
     */
    private $connection;

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

    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }
    public function setLoop($loop)
    {
        $this->loop = $loop;
        return $this;
    }

    public function getLoop()
    {
        return $this->loop;
    }

    /**
     *
     *
     * @param String $message
     * @param [type] $split
     * @param [type] $dataCoding
     * @return String
     */
    public function splitMessageString($message, $split, $dataCoding = DataCoding::DEFAULT_0)
    {
        switch ($dataCoding) {
            case DataCoding::DEFAULT_0:
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
        if (!isset($this->sarMsgRefNum)) {
            $this->sarMsgRefNum = mt_rand(0, $limit);
        }

        $this->sarMsgRefNum++;
        if ($this->sarMsgRefNum > $limit) {
            $this->sarMsgRefNum = 0;
        }

        return $this->sarMsgRefNum;
    }

    private function parse()
    {
        $c = $this->connection;
        if (count($parts = $this->getParts()) > 1) {
            $csmsReference = $this->getCsmsReference();
            $seqnum        = 1;
            foreach ($parts as $part) {
                $udh = pack('cccccc', 5, 0, 3, substr($csmsReference, 1, 1), count($parts), $seqnum);
                $this->setShortMessage($udh . $part)
                    ->setEsmClass(64)
                    ->setSequenceNumber($c->getNextSequenceNumber());
                $pduStr = $this->__toString();
                $this->loop->addTimer(0.1 * $seqnum, function () use ($pduStr, $c) {
                    $c->write($pduStr);
                });
                $seqnum++;
            }
            return true;
        }
        return $this->setSequenceNumber($c->getNextSequenceNumber())->submitSm();
    }

    public function submitSm()
    {
        $this->connection->write($this->__toString());
    }

    public function getParts()
    {
        $message    = $this->getShortMessage();
        $dataCoding = $this->getDataCoding();
        $msgLength  = strlen($message);
        $parts      = [$message];

        if ($msgLength > 160 && $dataCoding != DataCoding::UCS2 && $dataCoding != DataCoding::DEFAULT_0) {
            return false;
        }
        switch ($dataCoding) {
            case DataCoding::UCS2:
                $singleSmsOctetLimit = 140; // in octets, 70 UCS-2 chars
                $csmsSplit           = 132; // There are 133 octets available, but this would split the UCS the middle so use 132 instead
                break;
            case DataCoding::DEFAULT_0:
                $msgLength           = $this->gsm0338Length($message);
                $singleSmsOctetLimit = 160; // we send data in octets, but GSM 03.38 will be packed in septets (7-bit) by SMSC.
                $csmsSplit           = 152; // send 152 chars in each SMS since, we will use 16-bit CSMS ids (SMSC will format data)
                break;
            default:
                $singleSmsOctetLimit = 254; // From SMPP standard
                break;
        }
        if ($msgLength > $singleSmsOctetLimit) {
            $parts = $this->splitMessageString($message, $csmsSplit, $dataCoding);
        }
        return $parts;
    }

    public function gsm0338Length($utf8String)
    {
        $len = mb_strlen($utf8String, 'utf-8');
        $len += preg_match_all('/[\\^{}\\\~€|\\[\\]]/mu', $utf8String, $m);
        return $len;
    }

    public function send()
    {

        if (!$this->connection) {
            throw new Exception("Connection required", 1);
        }
        return $this->parse();
    }

}
