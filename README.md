# wordpress.plugin.contactform7.maxmessages
Limit the max amount of reactions on a ContactForm7 form

Because of the Corona-regulations you might organise an event with a maximum of 30 visitors.

The visitors can register themselves by filling in a ContactForm7 form on your Wordpress website.

In wp-admin at each form you have an extra tab 'Additional Settings'.

If you add:

s4u_max_reactions:30

you limit the maximum amount of registrations to 30.

Only active registrations are counted, spam- and trash registrations are subtracted.

You need the ContactForm7 plug-in: [https://wordpress.org/plugins/contact-form-7/ ContactForm7]

and the Flamingo plug-in (which is needed to count the reactions) [https://nl.wordpress.org/plugins/flamingo/ Flamingo]