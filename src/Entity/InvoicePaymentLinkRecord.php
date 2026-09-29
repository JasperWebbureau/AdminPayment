<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminPayment\Entity;

use Repository\RepositoryEntity;

/**
 * @FG\Entity[name=admin_invoice_payment_link,repository=Flexgrid\Modules\AdminPayment\Repository\InvoicePaymentLinkRecordRepository,type=Module,in_menu=false]
 * @FG\Index::tenant_invoice[columns={tenantId,invoicePublicId},unique=true]
 * @FG\Index::provider_link[columns={providerLinkId},unique=true]
 */
final class InvoicePaymentLinkRecord extends RepositoryEntity
{
    /** @FG\Column[type=primary] */ protected $id;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $tenantId;
    /** @FG\Column[type=varchar,length=64,required=true] */ protected $invoicePublicId;
    /** @FG\Column[type=varchar,length=32,required=true] */ protected $status;
    /** @FG\Column[type=varchar,length=128] */ protected $providerLinkId;
    /** @FG\Column[type=varchar,length=2048] */ protected $url;
    /** @FG\Column[type=bigint,required=true] */ protected $amountMinor;
    /** @FG\Column[type=varchar,length=3,required=true] */ protected $currency;
    /** @FG\Column[type=varchar,length=128] */ protected $paymentId;
    /** @FG\Column[type=text] */ protected $error;
    /** @FG\Column[type=bigint,required=true] */ protected $createdAt;
    /** @FG\Column[type=bigint,required=true] */ protected $updatedAt;


    // --- Auto-generated getters and setters ---

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @param mixed $value
     * @return $this
     */
    public function setId($value)
    {
        $this->id = $value;
        return $this;
    }

    /**
     * Get the value of tenantId
     */
    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     * Set the value of tenantId
     *
     * @param mixed $value
     * @return $this
     */
    public function setTenantId($value)
    {
        $this->tenantId = $value;
        return $this;
    }

    /**
     * Get the value of invoicePublicId
     */
    public function getInvoicePublicId()
    {
        return $this->invoicePublicId;
    }

    /**
     * Set the value of invoicePublicId
     *
     * @param mixed $value
     * @return $this
     */
    public function setInvoicePublicId($value)
    {
        $this->invoicePublicId = $value;
        return $this;
    }

    /**
     * Get the value of status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @param mixed $value
     * @return $this
     */
    public function setStatus($value)
    {
        $this->status = $value;
        return $this;
    }

    /**
     * Get the value of providerLinkId
     */
    public function getProviderLinkId()
    {
        return $this->providerLinkId;
    }

    /**
     * Set the value of providerLinkId
     *
     * @param mixed $value
     * @return $this
     */
    public function setProviderLinkId($value)
    {
        $this->providerLinkId = $value;
        return $this;
    }

    /**
     * Get the value of url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the value of url
     *
     * @param mixed $value
     * @return $this
     */
    public function setUrl($value)
    {
        $this->url = $value;
        return $this;
    }

    /**
     * Get the value of amountMinor
     */
    public function getAmountMinor()
    {
        return $this->amountMinor;
    }

    /**
     * Set the value of amountMinor
     *
     * @param mixed $value
     * @return $this
     */
    public function setAmountMinor($value)
    {
        $this->amountMinor = $value;
        return $this;
    }

    /**
     * Get the value of currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set the value of currency
     *
     * @param mixed $value
     * @return $this
     */
    public function setCurrency($value)
    {
        $this->currency = $value;
        return $this;
    }

    /**
     * Get the value of paymentId
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * Set the value of paymentId
     *
     * @param mixed $value
     * @return $this
     */
    public function setPaymentId($value)
    {
        $this->paymentId = $value;
        return $this;
    }

    /**
     * Get the value of error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set the value of error
     *
     * @param mixed $value
     * @return $this
     */
    public function setError($value)
    {
        $this->error = $value;
        return $this;
    }

    /**
     * Get the value of createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set the value of createdAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setCreatedAt($value)
    {
        $this->createdAt = $value;
        return $this;
    }

    /**
     * Get the value of updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set the value of updatedAt
     *
     * @param mixed $value
     * @return $this
     */
    public function setUpdatedAt($value)
    {
        $this->updatedAt = $value;
        return $this;
    }

    /**
     * Get the value of makeTime
     */
    public function getMakeTime()
    {
        return $this->makeTime;
    }

    /**
     * Set the value of makeTime
     *
     * @param mixed $value
     * @return $this
     */
    public function setMakeTime($value)
    {
        $this->makeTime = $value;
        return $this;
    }

}
