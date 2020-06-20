<?php

namespace alexeevdv\React\Smpp\Pdu;

use alexeevdv\React\Smpp\Exception\MalformedPdu;
use alexeevdv\React\Smpp\Exception\UnknownPdu;
use alexeevdv\React\Smpp\Utils\DataWrapper;

class Factory implements Contract\Factory
{
    private $classMap = [
        0x80000000 => GenericNack::class,
        0x00000001 => BindReceiver::class,
        0x80000001 => BindReceiverResp::class,
        0x00000002 => BindTransmitter::class,
        0x80000002 => BindTransmitterResp::class,
        0x00000003 => QuerySm::class,
        0x80000003 => QuerySmResp::class,
        0x00000004 => SubmitSm::class,
        0x80000004 => SubmitSmResp::class,
        0x00000005 => DeliverSm::class,
        0x80000005 => DeliverSmResp::class,
        0x00000006 => Unbind::class,
        0x80000006 => UnbindResp::class,
        0x00000007 => ReplaceSm::class,
        0x80000007 => ReplaceSmResp::class,
        0x00000008 => CancelSm::class,
        0x80000008 => CancelSmResp::class,
        0x00000009 => BindTransceiver::class,
        0x80000009 => BindTransceiverResp::class,
        0x00000015 => EnquireLink::class,
        0x80000015 => EnquireLinkResp::class,
    ];

    public function readBytes($buffer, $length, $postion = 0)
    {
        $data         = '';
        $initPosition = $postion;
        while (strlen($data) < $length) {
            $data .= $buffer[$initPosition];
            $initPosition++;
        }
        return ['d' => $data, 'p' => $initPosition];
    }

    public function createFromStreamBuffer(string $buffer): array
    {
        $wrapper = new DataWrapper($buffer);
        if ($wrapper->bytesLeft() < 16) {
            throw new MalformedPdu(bin2hex($buffer));
        }

        $pdu_queue = [];
        $length    = $wrapper->readInt32();
        if (strlen($buffer) !== $length && strlen($buffer) > $length) {
            $position = 0;
            do {
                $v = $this->readBytes($buffer, $length, $position);
                try {
                    $pdu_queue[] = $this->createFromBuffer($v['d']);
                } catch (\Throwable $th) {
                    //throw $th;
                }
                $position = $v['p'];
                $length   = 0;
                if ($v['p'] < strlen($buffer)) {
                    $w      = new DataWrapper(substr($buffer, $position));
                    $length = $w->readInt32();
                }
            } while ($length > 0);
        } else {
            $pdu_queue[] = $this->createFromBuffer($buffer);
        }
        return $pdu_queue;
    }
    
    public function createFromBuffer(string $buffer): Contract\Pdu
    {
        $wrapper = new DataWrapper($buffer);
        if ($wrapper->bytesLeft() < 16) {
            throw new MalformedPdu(bin2hex($buffer));
        }

        $length = $wrapper->readInt32();
        if (strlen($buffer) !== $length) {
            throw new MalformedPdu(bin2hex($buffer));
        }

        $id = $wrapper->readInt32();
        $status = $wrapper->readInt32();
        $sequence = $wrapper->readInt32();

        if (!isset($this->classMap[$id])) {
            throw new UnknownPdu(bin2hex($id));
        }

        $className = $this->classMap[$id];
        /** @var Pdu $pdu */
        $pdu = new $className(substr($buffer, 16));
        $pdu->setCommandStatus($status);
        $pdu->setSequenceNumber($sequence);
        return $pdu;
    }
}
