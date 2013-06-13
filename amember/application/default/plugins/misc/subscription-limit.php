<?php

class Am_Plugin_SubscriptionLimit extends Am_Plugin
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    function init()
    {
        $this->getDi()->productTable->customFields()->add(new Am_CustomFieldText('subscription_limit', 'Subscription limit', 'limit amount of subscription for this product, keep empty if you do not want to limit amount of subscriptions'));
    }


    function onInvoiceBeforeInsert(Am_Event $event)
    {
        /* @var $invoice Invoice */
        $invoice = $event->getInvoice();
        foreach ($invoice->getItems() as $item)
        {
            $product = $this->getDi()->productTable->load($item->item_id);
            if (($limit = $product->data()->get('subscription_limit')) &&
                $limit < $item->qty)
            {
                throw new Am_Exception_InputError(sprintf('There is not such amount (%d) of product %s', $item->qty, $item->item_title));
            }
        }

    }

    function onInvoiceStarted(Am_Event_InvoiceStarted $event)
    {
        $invoice = $event->getInvoice();
        foreach ($invoice->getItems() as $item)
        {
            $product = $this->getDi()->productTable->load($item->item_id);

            if ($limit = $product->data()->get('subscription_limit'))
            {
                $limit -= $item->qty;
                $product->data()->set('subscription_limit', $limit);
                if (!$limit)
                {
                    $product->is_disabled = 1;
                }
                $product->save();
            }
        }
    }

    function getReadme()
    {
        return <<<CUT
This plugin allows you to limit amount of available
subscription for specific product. The product will
be disabled in case of limit reached.

You can set up limit in product settings
aMember CP -> Products -> Manage Products -> Edit (Subscription limit)
CUT;
    }

}
