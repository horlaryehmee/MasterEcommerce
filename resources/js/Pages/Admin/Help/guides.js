/**
 * Help Center content: plain-language guides with screenshots.
 *
 * Screenshots live in public/help/<name>.webp (taken from the demo website with
 * fictional data). Names starting with "desktop-" are computer screenshots; all
 * others are phone screenshots. In text, **double stars** make words bold.
 *
 * shop: true = only relevant when online ordering is switched on.
 */
import { BarChart3, CircleHelp, FolderTree, LayoutTemplate, Package, Rocket, Settings, ShieldCheck, ShoppingBag } from 'lucide-react';

export const SECTIONS = [
    {
        id: 'start',
        title: 'Getting started',
        icon: Rocket,
        color: 'bg-sky-500',
        guides: [
            {
                id: 'find-your-way',
                title: 'Find your way around',
                summary: 'Where everything is, on your phone and on a computer.',
                steps: [
                    { t: 'On your **phone**, the main sections are in the bar at the **bottom of the screen**: **Home**, **Orders**, **Products** and **Settings**. Tap one to open it. A small orange number on **Orders** tells you how many new orders are waiting.', img: 'overview' },
                    { t: 'Everything else is under **More** (the last button on the bottom bar). Tap it to see Analytics, Customers, Coupons, Reviews, Categories, Templates, Website Content, Updates, Profile and Help. Tap the **✕** or swipe down to close it.', img: 'more-menu' },
                    { t: 'On a **computer**, every section is listed in the menu on the **left side** of the screen. Click a name to open it.', img: 'desktop-overview' },
                    { t: 'To see your website the way customers see it, tap the **square-with-arrow icon** at the top right of the screen (on a computer: **View website** at the bottom of the left menu). It opens in a new tab, so the admin stays open.' },
                ],
                tips: ['Stuck? Tap the **?** at the top of the screen on any page to open these guides.', 'Pages with a **← Back** button at the top take you one step back. You can also use your phone’s normal back gesture.', 'A green message at the bottom of the screen means your change was saved. A red message means something needs fixing: look for red text under the fields.'],
            },
            {
                id: 'sign-in',
                title: 'Sign in and sign out',
                summary: 'Logging in, forgotten passwords and logging out.',
                steps: [
                    { t: 'Go to your website address followed by **/admin** (for example **yourshop.com/admin**). Enter your **email** and **password**, then tap **Sign in**.', img: 'login' },
                    { t: 'Forgot your password? Tap **Forgot password?** on the sign-in page, enter your email, and we’ll email you a link to choose a new one. The link works for 60 minutes.' },
                    { t: 'To sign out: on a phone tap **More**, then **Log out** at the bottom. On a computer, click **Logout** in the left menu.' },
                ],
                tips: ['If you turned on two-factor sign-in, you’ll also be asked for the 6-digit code from your authenticator app. See **Protect your account with two-factor sign-in**.'],
            },
            {
                id: 'install-app',
                title: 'Put the admin on your phone like an app',
                summary: 'Open your dashboard from an icon on your home screen.',
                steps: [
                    { t: 'Open your admin in **Chrome** (Android) or **Safari** (iPhone) and sign in.' },
                    { t: '**Android:** tap **More** at the bottom, then **Install admin app**, then **Install**. The icon appears on your home screen.', img: 'install-app' },
                    { t: '**iPhone:** tap the **Share** button in Safari (the square with an arrow pointing up), scroll down and tap **Add to Home Screen**, then **Add**.' },
                    { t: 'From now on, open the admin from the new icon. It opens full screen, like any other app, and you stay signed in.' },
                ],
                tips: ['On a computer, Chrome and Edge show an **install icon** in the address bar, or use **Install admin app** in the left menu.'],
            },
            {
                id: 'notifications',
                title: 'Get an alert on your phone for every new order',
                summary: 'Phone notifications for orders, payments to check and new reviews.',
                steps: [
                    { t: 'When you open the admin, a card asks **Get notified when an order comes in**. Tap **Turn on**, then **Allow** when your phone asks.', img: 'push-prompt' },
                    { t: 'That’s it. You’ll get a notification for every new order, every payment you need to check, and every review waiting for you, even when the admin is closed. Tap a notification to open that order.' },
                    { t: 'To turn notifications on or off later, or see which devices get them, open **More → Profile → Phone notifications**. You can send yourself a **test notification** there.', img: 'profile-notifications' },
                ],
                tips: ['On iPhone, notifications only work after you add the admin to your home screen (see the guide above) and open it from that icon.', 'Turn notifications on separately on each phone or computer you use.'],
            },
        ],
    },
    {
        id: 'orders',
        title: 'Orders',
        icon: ShoppingBag,
        color: 'bg-amber-500',
        shop: true,
        guides: [
            {
                id: 'see-orders',
                title: 'See and find your orders',
                summary: 'Your list of orders, what the colours mean, and searching.',
                steps: [
                    { t: 'Tap **Orders** on the bottom bar. The newest orders are at the top. Each order shows the customer, the total, how they’re paying and its status.', img: 'orders-list' },
                    { t: 'The buttons at the top (**All**, **New**, **Confirmed**, **Out for delivery**…) show only orders with that status. The number on each button is how many there are.' },
                    { t: 'Use the **search box** to find an order by its number, the customer’s name, phone number or email. Tap **Filters** to show only paid or unpaid orders, a payment method, or a date range.' },
                    { t: 'On a computer you see the same list as a table.', img: 'desktop-orders' },
                ],
                tips: ['Tap the **download icon** next to Filters to download your orders as a spreadsheet (CSV), for example for your accountant.', 'Tick the boxes next to several orders to change their status, or delete them, all at once.'],
            },
            {
                id: 'handle-order',
                title: 'Handle a new order, step by step',
                summary: 'From “New” to “Completed”, and keeping the customer informed.',
                steps: [
                    { t: 'Tap the order to open it. You’ll see what was ordered, the delivery address and the customer’s details.' },
                    { t: 'Under **Update order**, tap the status that fits: **New** (just came in) → **Confirmed** (you’ve accepted it) → **Out for delivery** → **Completed** (delivered). Use **Cancelled** if the order won’t go ahead.', img: 'order-update' },
                    { t: 'If the customer gave an email address, they get an email automatically each time you change the status, so they always know what’s happening.' },
                    { t: 'Need to talk to the customer? Use the **Call** and **WhatsApp** buttons at the bottom of the order.', img: 'order-contact' },
                ],
                tips: ['Under **Payment**, set **Paid** once you’ve received the money. Paid orders can’t be deleted (cancel or refund them instead), which keeps your records safe.', '**Awaiting payment** means the customer started paying online but hasn’t finished. Don’t send these. They become **New** automatically if the customer pays, and are cancelled after 24 hours if they don’t.'],
            },
            {
                id: 'bank-transfer',
                title: 'Check a bank transfer payment',
                summary: 'Confirm the money arrived, then mark the order as paid.',
                steps: [
                    { t: 'When a customer pays by bank transfer, they see your bank details, can upload their receipt, and press **I’ve made the payment**. You get a notification, and a blue box appears at the top of **Orders**. Tap **View**.', img: 'orders-banner' },
                    { t: 'Open the order. The blue box tells you the customer says they’ve paid, and when.', img: 'order-bank-banner' },
                    { t: '**Open your bank app and check that the money really arrived.** Receipts can be faked, so the bank is the only proof.' },
                    { t: 'If they uploaded a receipt, it’s under **Payment receipts**. Tap it to see it full size. If the money is in your account, tap **Confirm payment**. The order is marked **Paid** and the customer gets an email.', img: 'order-receipt' },
                    { t: 'If the money isn’t there, tap **Reject** and choose a reason (for example **No payment received yet**). The customer sees your reason and can upload a new receipt.' },
                    { t: 'No receipt? Check your bank, then tap **Money received: mark as paid** in the blue box.' },
                ],
                tips: ['Customers are asked to use the order number (for example **#1042**) as the transfer reference, so you can match payments easily.'],
            },
            {
                id: 'paystack',
                title: 'Online card payments (Paystack)',
                summary: 'Take card, bank and USSD payments online, safely.',
                steps: [
                    { t: 'Create a free account at **paystack.com**. In your Paystack dashboard, go to **Settings → API Keys & Webhooks**.' },
                    { t: 'In the admin, open **Settings → Shop & checkout**. Switch on **Pay online (Paystack)** under **Payment options** and paste your **test** keys first.', img: 'settings-payments' },
                    { t: 'While test keys are in use, a yellow **TEST MODE** bar shows across the top of the admin. Place a test order on your website to make sure everything works. Test payments are not real money.' },
                    { t: 'When you’re ready, paste your **live** keys, switch the mode to **Live** and save. The yellow bar disappears and customers can pay for real.' },
                ],
                tips: ['Paystack orders are marked **Paid** automatically, but only after the payment has been checked with Paystack. Never mark a Paystack order as paid by hand.', 'If you paste a test key in a live box (or the other way round), the admin warns you and won’t save it.'],
            },
        ],
    },
    {
        id: 'products',
        title: 'Products',
        icon: Package,
        color: 'bg-violet-500',
        guides: [
            {
                id: 'add-product',
                title: 'Add a product',
                summary: 'Photos, name, price and putting it on your website.',
                steps: [
                    { t: 'Tap **Products** on the bottom bar, then **Add product**.', img: 'products-list' },
                    { t: 'Under **Images**, tap to add photos from your phone (up to 10). The first photo is the main one customers see. Photos are made smaller and faster automatically, without losing quality.', img: 'product-images' },
                    { t: 'Under **Product details**, type the **Product name** and a **Short description** (one or two sentences shown near the price). The **Full description** is optional: use it for features, sizes and what’s in the box.', img: 'product-details' },
                    { t: 'Under **Pricing**, enter the **Current price**. If the product is on sale, put the old price in **Previous price**: it shows crossed out next to the new one.', img: 'product-pricing' },
                    { t: 'Under **Visibility**, choose **Published** to show it on your website, or **Draft** to keep working on it privately. Switch on **Featured product** to show it on your homepage, or **New arrival** for the New Arrivals section.', img: 'product-visibility' },
                    { t: 'Choose a **Category** and the **Availability** (In Stock, Limited Stock, Pre-order…), then tap **Save** at the bottom of the screen.' },
                ],
                tips: ['On a computer the form is the same, laid out in two columns.'],
                more: [{ img: 'desktop-product', caption: 'Editing a product on a computer.' }],
            },
            {
                id: 'variations',
                title: 'Sizes, colours and other options',
                summary: 'Sell one product in several versions, each with its own price and stock.',
                steps: [
                    { t: 'Open a product (or add a new one) and scroll to **Variations**.', img: 'product-variations' },
                    { t: 'Add an option, for example **Size** (or tap one of the **Quick add** buttons). Type each value, for example **Small**, and press **Enter** after each one. Add another option, like **Colour**, if you need it.' },
                    { t: 'A line is created for each combination. Give each one its own price, availability or photo if it’s different, then tap **Save**.' },
                ],
                tips: ['Customers choose the option on the product page before adding it to their cart.'],
            },
            {
                id: 'edit-products',
                title: 'Edit, hide or delete products',
                summary: 'Change details, take products off your website, and do many at once.',
                steps: [
                    { t: 'Tap a product in the list to change anything about it, then tap **Save**.' },
                    { t: 'To take a product off your website without deleting it, set it to **Draft**. Set it back to **Published** to show it again.' },
                    { t: 'To change many products at once, tick the boxes next to them. A black bar appears at the bottom: tap the **eye** to publish, the **crossed-out eye** to hide, the **bin** to delete, or **More** for other actions.', img: 'products-bulk' },
                ],
                tips: ['Changed a product’s name? Old links to it keep working: visitors are sent to the new address automatically.'],
            },
        ],
    },
    {
        id: 'catalogue',
        title: 'Categories, coupons & reviews',
        icon: FolderTree,
        color: 'bg-emerald-500',
        guides: [
            {
                id: 'categories',
                title: 'Group products into categories',
                summary: 'Make it easy for customers to browse, for example Phones, Fashion, Kitchen.',
                steps: [
                    { t: 'Tap **More → Categories**, then **Add category**.', img: 'categories-list' },
                    { t: 'Type a **Name** and, if you like, a short description and an image.' },
                    { t: 'Under **Display**, keep **Visible on website** on. Switch on **Show on homepage** to list it in the “Shop by Category” section. **Display order** decides the order: lower numbers come first.', img: 'category-form' },
                    { t: 'Tap **Save**. Then open any product and choose this category for it.' },
                ],
            },
            {
                id: 'coupons',
                title: 'Create a discount coupon',
                summary: 'Codes like WELCOME10 that customers type at checkout.',
                shop: true,
                steps: [
                    { t: 'Tap **More → Coupons**, then **Add coupon**.', img: 'coupons-list' },
                    { t: 'Type the **Coupon code** customers will enter (or tap **Generate** to make a random one). Choose the **Discount type**: a **percentage** off, or a fixed amount off the whole cart or each product. Enter the amount.', img: 'coupon-form' },
                    { t: 'Optional: set **Starts on** / **Expires on** dates, a **Minimum spend**, which products it works on, and how many times it can be used.' },
                    { t: 'Make sure **Coupon is on**, then tap **Save**. Share the code with your customers. They type it in the cart or at checkout.' },
                ],
                tips: ['Each coupon page shows how many times it has been used and how much discount it has given.', 'Customers can use one coupon per order.'],
            },
            {
                id: 'reviews',
                title: 'Publish customer reviews',
                summary: 'Approve reviews before they appear, and control where they show.',
                steps: [
                    { t: 'When a customer writes a review, it waits for you. Tap **More → Reviews** (the number shows how many are waiting).' },
                    { t: 'Read each review. Tap **Publish** to show it on your website, **Reject** to hide it, or **Spam** for junk. Tick several to do them all at once.', img: 'reviews-list' },
                    { t: 'At the top, under **Review settings**, you can turn reviews off completely, turn off the **homepage** reviews section, or let reviews from **verified buyers** publish straight away.', img: 'reviews-settings' },
                ],
                tips: ['The homepage “What our customers say” section appears by itself once you have 5 published 4- or 5-star reviews.', 'Spam is blocked automatically. Anything suspicious is held for you, with the reason shown.', 'A **Verified purchase** badge means the reviewer used the same email address as an order for that product.'],
            },
            {
                id: 'customers',
                title: 'See your customers',
                summary: 'Who has ordered, how often and how much.',
                shop: true,
                steps: [
                    { t: 'Tap **More → Customers**. Everyone who has placed an order is listed, with their number of orders and total spent.', img: 'customers-list' },
                    { t: 'Tap a customer to see all their orders and contact them by phone or WhatsApp.', img: 'customer-detail' },
                ],
                tips: ['Use **Filters** to find, for example, customers in one state or who spent over a certain amount. The download button exports the list.'],
            },
        ],
    },
    {
        id: 'website',
        title: 'Your website’s look & pages',
        icon: LayoutTemplate,
        color: 'bg-pink-500',
        guides: [
            {
                id: 'homepage',
                title: 'Change your homepage',
                summary: 'Banners, sections, texts and the footer.',
                steps: [
                    { t: 'Tap **More → Website Content**. The list is in the same order as your homepage, from top to bottom.', img: 'content-menu' },
                    { t: 'Tap a section to change it. For example, **Hero banner & slider** is the big picture at the top: change the photos, the headline, the button text and where the button goes.', img: 'content-hero' },
                    { t: 'Every section can be switched off if you don’t need it. Tap **Save** at the bottom when you’re done, then open your website to see the change.' },
                ],
                tips: ['Use photos at least 1600 pixels wide for banners so they look sharp on computers. Banners can have a separate photo for phones.'],
            },
            {
                id: 'pages',
                title: 'Edit your pages (About, FAQ, policies)',
                summary: 'The pages linked at the bottom of your website.',
                steps: [
                    { t: 'Tap **More → Website Content → Pages**.', img: 'content-pages' },
                    { t: 'Tap a page, such as **About Us**, **Frequently Asked Questions**, **Shipping and Delivery**, **Privacy Policy** or **Terms and Conditions**. Change the text and tap **Save**.' },
                    { t: 'Switch off **Published** to hide a page you don’t need.' },
                ],
            },
            {
                id: 'templates',
                title: 'Change your website’s design',
                summary: 'Pick a ready-made design. Your products and content stay the same.',
                steps: [
                    { t: 'Tap **More → Templates**. Each template is shown with your own products.', img: 'templates' },
                    { t: 'Tap **Preview** to see a template full size. Nothing changes on your website until you choose one.' },
                    { t: 'Tap **Use template** on the one you like. Your website switches to the new design straight away.' },
                ],
                tips: ['Preview links can be copied and sent to anyone, for example to ask a friend which design they prefer.', 'To change colours and fonts, see **Settings → Branding**.'],
            },
        ],
    },
    {
        id: 'settings',
        title: 'Settings',
        icon: Settings,
        color: 'bg-zinc-700',
        guides: [
            {
                id: 'business-details',
                title: 'Your business details and logo',
                summary: 'Name, logo, phone, address and opening hours.',
                steps: [
                    { t: 'Tap **Settings** on the bottom bar. Settings are grouped: **Your business**, **Selling**, **Marketing** and **Advanced**.', img: 'settings-menu' },
                    { t: 'Tap **Business information**. Enter your business name, upload your **logo**, and fill in your phone, email, address and opening hours. They appear in your website’s header, footer and contact page.', img: 'settings-business' },
                    { t: 'Tap **Save** at the bottom.' },
                ],
                tips: ['**Branding** changes your website’s colours and fonts. **Currency** sets the money symbol and how prices look (for example ₦1,000).'],
            },
            {
                id: 'payment-options',
                title: 'Choose how customers can pay',
                summary: 'WhatsApp, pay on delivery, bank transfer and Paystack.',
                shop: true,
                steps: [
                    { t: 'Open **Settings → Shop & checkout** and scroll to **Payment options**.', img: 'settings-payments' },
                    { t: 'Switch on the ways you accept: **Confirm on WhatsApp**, **Pay on delivery**, **Bank transfer** and/or **Pay online (Paystack)**.' },
                    { t: 'For bank transfer, fill in your **Bank name**, **Account name** and **Account number**. Customers see them after they order.' },
                    { t: 'Tap **Save shop settings**.' },
                ],
                tips: ['On the same page you can switch online ordering on or off, and choose which order emails are sent.'],
            },
            {
                id: 'shipping',
                title: 'Delivery areas and fees',
                summary: 'Where you deliver, how much it costs, free delivery and pickup.',
                shop: true,
                steps: [
                    { t: 'Open **Settings → Shipping**. First choose which countries you deliver to.' },
                    { t: 'Under **Shipping zones**, tap to add a zone, for example **Lagos** or **Rest of Nigeria**, and choose the states it covers.', img: 'settings-shipping' },
                    { t: 'Add delivery methods to the zone: a **flat rate** (fixed fee), **free shipping** (for example above a minimum order amount), or **local pickup** with your pickup address. Save.' },
                ],
                tips: ['Customers only see the methods for the address they enter at checkout.'],
            },
            {
                id: 'whatsapp',
                title: 'Your WhatsApp number',
                summary: 'Every WhatsApp button on your website opens a chat with you.',
                steps: [
                    { t: 'Open **Settings → WhatsApp**. Enter your WhatsApp number with the country code (for example **2348012345678**).', img: 'settings-whatsapp' },
                    { t: 'Change the ready-made messages if you like. Customers can still edit them before sending. Tap **Save**.' },
                ],
            },
            {
                id: 'email',
                title: 'Make sure emails are delivered',
                summary: 'Order emails to you and your customers.',
                steps: [
                    { t: 'Open **Settings → Email**. Your web host can give you the email server (SMTP) details. On cPanel they’re under **Email Accounts → Connect Devices**.' },
                    { t: 'Fill them in, save, then use **Send a test email**. If it arrives, emails are working.', img: 'settings-email' },
                ],
                tips: ['Not getting emails? Check your spam folder first, then send a test email to see the exact error.', 'For safety, saving email settings asks you to confirm your password (at most once every 10 minutes).'],
            },
            {
                id: 'seo',
                title: 'Look good on Google and when sharing links',
                summary: 'Your website’s title, description and sharing image.',
                steps: [
                    { t: 'Open **Settings → SEO**. Write a clear **title** and **description** of your business: this is what people see in Google results.', img: 'settings-seo' },
                    { t: 'Upload a **social sharing image**. It shows when your website link is shared on WhatsApp, Facebook or X. Tap **Save**.' },
                ],
                tips: ['Products, categories and pages each have their own **Search engine listing** box if you want to set them one by one. Otherwise they are filled in automatically.'],
            },
        ],
    },
    {
        id: 'analytics',
        title: 'Analytics',
        icon: BarChart3,
        color: 'bg-indigo-500',
        guides: [
            {
                id: 'analytics',
                title: 'See how many people visit',
                summary: 'Visitors, popular pages, where people come from, and sales.',
                steps: [
                    { t: 'Tap **More → Analytics**. Choose a period at the top (today, last 7 days, last 30 days…).', img: 'analytics' },
                    { t: 'You’ll see how many **visitors** you had, which pages and products they looked at, where they came from (Google, Instagram, WhatsApp…), which country and device they used, and how many added to cart and ordered.' },
                ],
                tips: ['These statistics are built in and private: no extra setup is needed.'],
            },
        ],
    },
    {
        id: 'account',
        title: 'Your account & security',
        icon: ShieldCheck,
        color: 'bg-rose-500',
        guides: [
            {
                id: 'password',
                title: 'Change your name, email or password',
                summary: 'Keep your login details up to date.',
                steps: [
                    { t: 'Tap **More → Profile**. Change your name under **Account details** and tap **Save details**.' },
                    { t: 'To change your **login email**, type the new address. For safety you’re asked for your **current password**, and we email a code to the **new** address. Enter it under **Verify your new email**. Your old address also gets a message about the change.' },
                    { t: 'Under **Change password**, type your current password, then the new one twice, and save. Other devices are signed out for safety.', img: 'profile-password' },
                ],
            },
            {
                id: 'two-factor',
                title: 'Protect your account with two-factor sign-in',
                summary: 'Nobody can sign in without your phone, even if they know your password.',
                steps: [
                    { t: 'Install a free authenticator app on your phone, such as **Google Authenticator** or **Microsoft Authenticator**.' },
                    { t: 'Tap **More → Profile** and find **Two-factor sign-in**. Tap **Turn on** and enter your password.', img: 'profile-2fa' },
                    { t: 'Scan the QR code with the authenticator app, then type the 6-digit code it shows.' },
                    { t: '**Save your recovery codes** somewhere safe (write them down). If you lose your phone, a recovery code lets you sign in.' },
                ],
                tips: ['After this, signing in asks for your password and then the 6-digit code from the app.'],
            },
            {
                id: 'updates',
                title: 'Update your website',
                summary: 'Get new features and fixes safely.',
                steps: [
                    { t: 'When a new version is available, **Updates** shows **New**. Tap **More → Updates**.', img: 'updates' },
                    { t: 'Read what’s new, then tap the update button. A backup is made first, and your website keeps working during the update.' },
                ],
                tips: ['If anything goes wrong, the update is undone automatically and your website goes back to how it was.'],
            },
        ],
    },
    {
        id: 'faq',
        title: 'Common questions',
        icon: CircleHelp,
        color: 'bg-teal-500',
        guides: [
            {
                id: 'faq',
                title: 'Quick answers',
                summary: 'Fixes for the things people ask about most.',
                faq: [
                    ['I made a change but don’t see it on my website.', 'Make sure you tapped **Save** and saw the green “saved” message. Then refresh the website page (pull down on your phone). Products only show when they are **Published**.'],
                    ['A customer says they paid but I can’t see the money.', 'Never mark an order as paid until the money is in your bank account. Reject the receipt with a reason like **No payment received yet**, or call the customer using the button on the order.'],
                    ['I’m not getting order emails.', 'Check your spam folder. Then open **Settings → Email** and send a test email: it shows what’s wrong. You can also turn on phone notifications (**More → Profile**).'],
                    ['A photo won’t upload.', 'Use a JPG, PNG or WebP photo under 10 MB. Very large phone photos are fine, they are made smaller automatically. If it still fails, take a screenshot of the photo and upload that.'],
                    ['I forgot my password.', 'On the sign-in page, tap **Forgot password?** and follow the link we email you.'],
                    ['I lost the phone with my authenticator app.', 'On the sign-in page, choose to use a **recovery code** instead of the 6-digit code. Then turn two-factor sign-in off and on again with your new phone.'],
                    ['How do I stop taking orders for a while?', 'Open **Settings → Shop & checkout** and switch off online ordering. Your products stay visible and customers can still contact you on WhatsApp.'],
                    ['What does the yellow TEST MODE bar mean?', 'Paystack is using test keys, so online payments aren’t real. Paste your live keys in **Settings → Shop & checkout** and switch to **Live**.'],
                ],
            },
        ],
    },
];

/** Plain text of a guide, for search. */
export function guideText(guide) {
    return [guide.title, guide.summary, ...(guide.steps ?? []).map((s) => s.t), ...(guide.tips ?? []), ...(guide.faq ?? []).flat()].join(' ').replace(/\*\*/g, '').toLowerCase();
}