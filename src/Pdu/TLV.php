<?php

namespace alexeevdv\React\Smpp\Pdu;

class TLV implements Contract\TLV
{
    const DEST_ADDR_SUBUNIT           = 0x0005;
    const DEST_NETWORK_TYPE           = 0x0006;
    const DEST_BEARER_TYPE            = 0x0007;
    const DEST_TELEMATICS_ID          = 0x0008;
    const SOURCE_ADDR_SUBUNIT         = 0x000D;
    const SOURCE_NETWORK_TYPE         = 0x000E;
    const SOURCE_BEARER_TYPE          = 0x000F;
    const SOURCE_TELEMATICS_ID        = 0x0010;
    const QOS_TIME_TO_LIVE            = 0x0017;
    const PAYLOAD_TYPE                = 0x0019;
    const ADDITIONAL_STATUS_INFO_TEXT = 0x001D;
    const RECEIPTED_MESSAGE_ID        = 0x001E;
    const MS_MSG_WAIT_FACILITIES      = 0x0030;
    const PRIVACY_INDICATOR           = 0x0201;
    const SOURCE_SUBADDRESS           = 0x0202;
    const DEST_SUBADDRESS             = 0x0203;
    const USER_MESSAGE_REFERENCE      = 0x0204;
    const USER_RESPONSE_CODE          = 0x0205;
    const SOURCE_PORT                 = 0x020A;
    const DESTINATION_PORT            = 0x020B;
    const SAR_MSG_REF_NUM             = 0x020C;
    const LANGUAGE_INDICATOR          = 0x020D;
    const SAR_TOTAL_SEGMENTS          = 0x020E;
    const SAR_SEGMENT_SEQNUM          = 0x020F;
    const SC_INTERFACE_VERSION        = 0x0210;
    const CALLBACK_NUM_PRES_IND       = 0x0302;
    const CALLBACK_NUM_ATAG           = 0x0303;
    const NUMBER_OF_MESSAGES          = 0x0304;
    const CALLBACK_NUM                = 0x0381;
    const DPF_RESULT                  = 0x0420;
    const SET_DPF                     = 0x0421;
    const MS_AVAILABILITY_STATUS      = 0x0422;
    const NETWORK_ERROR_CODE          = 0x0423;
    const MESSAGE_PAYLOAD             = 0x0424;
    const DELIVERY_FAILURE_REASON     = 0x0425;
    const MORE_MESSAGES_TO_SEND       = 0x0426;
    const MESSAGE_STATE               = 0x0427;
    const USSD_SERVICE_OP             = 0x0501;
    const DISPLAY_TIME                = 0x1201;
    const SMS_SIGNAL                  = 0x1203;
    const MS_VALIDITY                 = 0x1204;
    const ALERT_ON_MESSAGE_DELIVERY   = 0x130C;
    const ITS_REPLY_TYPE              = 0x1380;
    const ITS_SESSION_INFO            = 0x1383;
    private $sizeMap                  = [
        self::DEST_ADDR_SUBUNIT           => ['value' => 1],
        self::SOURCE_ADDR_SUBUNIT         => ['value' => 1],
        self::DEST_NETWORK_TYPE           => ['value' => 1],
        self::SOURCE_NETWORK_TYPE         => ['value' => 1],
        self::DEST_BEARER_TYPE            => ['value' => 1],
        self::SOURCE_BEARER_TYPE          => ['value' => 1],
        self::DEST_TELEMATICS_ID          => ['value' => 2],
        self::SOURCE_TELEMATICS_ID        => ['value' => 1],
        self::QOS_TIME_TO_LIVE            => ['value' => 4],
        self::PAYLOAD_TYPE                => ['value' => 1],
        self::ADDITIONAL_STATUS_INFO_TEXT => ['value' => null],
        self::RECEIPTED_MESSAGE_ID        => ['value' => null],
        self::MS_MSG_WAIT_FACILITIES      => ['value' => 1],
        self::PRIVACY_INDICATOR           => ['value' => 1],
        self::SOURCE_SUBADDRESS           => ['value' => null],
        self::DEST_SUBADDRESS             => ['value' => null],
        self::USER_MESSAGE_REFERENCE      => ['value' => 2],
        self::USER_RESPONSE_CODE          => ['value' => 1],
        self::LANGUAGE_INDICATOR          => ['value' => 1],
        self::SOURCE_PORT                 => ['value' => 2],
        self::DESTINATION_PORT            => ['value' => 2],
        self::SAR_MSG_REF_NUM             => ['value' => 2],
        self::SAR_TOTAL_SEGMENTS          => ['value' => 1],
        self::SAR_SEGMENT_SEQNUM          => ['value' => 1],
        self::SC_INTERFACE_VERSION        => ['value' => 1],
        self::DISPLAY_TIME                => ['value' => 1],
        self::MS_VALIDITY                 => ['value' => 1],
        self::DPF_RESULT                  => ['value' => 1],
        self::SET_DPF                     => ['value' => 1],
        self::MS_AVAILABILITY_STATUS      => ['value' => 1],
        self::NETWORK_ERROR_CODE          => ['value' => 3, 'type' => 'string'],
        self::MESSAGE_PAYLOAD             => ['value' => '*', 'type' => 'string'],
        self::DELIVERY_FAILURE_REASON     => ['value' => 1],
        self::MORE_MESSAGES_TO_SEND       => ['value' => 1],
        self::MESSAGE_STATE               => ['value' => 1],
        self::CALLBACK_NUM                => ['value' => '*', 'type' => 'string'],
        self::CALLBACK_NUM_PRES_IND       => ['value' => 1],
        self::CALLBACK_NUM_ATAG           => ['value' => '*', 'type' => 'string'],
        self::NUMBER_OF_MESSAGES          => ['value' => 1],
        self::SMS_SIGNAL                  => ['value' => 2],
        self::ALERT_ON_MESSAGE_DELIVERY   => ['value' => 0],
        self::ITS_REPLY_TYPE              => ['value' => 1],
        self::USSD_SERVICE_OP             => ['value' => 1, 'type' => 'string'],
        self::ITS_SESSION_INFO            => ['value' => 2],
    ];
    /**
     * @var int
     */
    private $tag;

    /**
     * @var string
     */
    private $value;
    /**
     * @var int
     */
    private $length;

    public function __construct(int $tag, string $value)
    {
        $this->tag    = $tag;
        $this->value  = $value;
    }

    public function getTag(): int
    {
        return $this->tag;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getLength(): int
    {
        return $this->sizeMap[$this->tag]['value'];
    }
    public function getMap(): int
    {
        return $this->sizeMap[$this->tag];
    }
}
