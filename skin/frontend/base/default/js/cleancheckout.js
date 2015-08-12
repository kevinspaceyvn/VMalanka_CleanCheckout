/**
 * @category    design
 * @package     base_default
 * @author      Vasyl Malanka <vasyl.malanka@yahoo.com>
 */
var CleanCheckout = Class.create(Checkout, {
    initialize: function($super, accordion, urls) {
        $super(accordion, urls);
        this.steps = ['review'];
    }
});

var CleanCheckoutReview = Class.create(Review, {
    initialize: function($super, saveUrl, successUrl, agreementsForm) {
        $super(saveUrl, successUrl, agreementsForm);
    },
    save: function() {
        checkout.setLoadWaiting('review');
        var params = 'payment[method]=checkmo';
        params.save = true;
        var request = new Ajax.Request(
            this.saveUrl,
            {
                method: 'post',
                parameters: params,
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout)
            }
        );
    }
});
