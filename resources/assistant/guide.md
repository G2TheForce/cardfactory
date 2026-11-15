# SmartCard Studio guide

This is the guide the in-app assistant answers from. Keep it in plain words, the way a
user sees the app (menu names, button labels), and update it when features change.

## Roles

- **Super Admin** can do everything.
- **Admin** manages users, customers, themes, designs, profiles, landing pages, NFC cards,
  inventory, orders, exports, bulk imports and analytics.
- **Designer** edits themes and the card designs assigned to them, and runs exports.
- **Sales Manager** manages customers, NFC cards, inventory, orders, bulk imports and analytics.
- **Customer** edits their own card details, designs, digital profile and landing pages,
  and sees their own analytics.

Admins change what each role can do under **Administration → Roles & permissions**, and add
or deactivate accounts under **Administration → Users**.

## Dashboard

The **Dashboard** shows totals (customers, designs, profiles, NFC cards, orders and revenue for
staff), recent activity and products that are low on stock.

## Card designs

**Design → Card designs** lists designs. **New design** starts from a theme or a blank card
(ISO/CR80, US, EU, Japan or square sizes, landscape or portrait) and can be attached to a
customer so their details fill the card.

In the editor:
- Left panels: **Elements** (shapes, lines, QR code, logo and photo slots, patterns, social
  icons, 1,500+ icons), **Text**, **Uploads** (images and custom fonts), **Background** (solid,
  gradient, image with tint, palette), **Layers** (order, lock, hide) and **Fields** (merge
  fields such as name, job title, phone; show customer data, sample data or the raw tags).
- Drag, resize and rotate items; they snap to the card centre, trim and safe area. Arrow keys nudge.
- Undo/redo, copy/paste, duplicate, align, zoom, front/back switch, bleed and safe-area
  guides, flip preview and card settings (size, orientation, bleed, corner radius, status).
- Designs save automatically every 30 seconds. If someone else saved a newer version, you're
  asked to reload instead of overwriting their work.
- Uploads: JPG, PNG or WebP up to 10 MB. SVG images are not accepted. Fonts: TTF, OTF, WOFF, WOFF2 up to 5 MB.

## Exports (printing files)

Open a design and choose **Export** (editor top bar or the designs list). **Design → Exports**
lists finished files.
- **PDF**: print-ready vector PDF with bleed and trim boxes, optional crop marks and CMYK.
- **PNG**: 150, 300 or 600 DPI, optional transparent background.
- **SVG**: vector file with images and fonts embedded.
- Bleed can be 0–5 mm (3 mm is standard). An 85.6 × 54 mm card with 3 mm bleed at 300 DPI is 1082 × 709 px.
- Only the person who ran an export (or staff with export rights) can download it. Files are
  deleted after a few days, so download them soon.
- If PDF or PNG are greyed out, the server is missing Chrome/Chromium; if CMYK is greyed out,
  Ghostscript is missing. An administrator needs to install them.

## Themes

**Design → Themes** is the library of ready-made card designs, including premium portrait
photo-card themes (bakery & café, consulting, fine dining, fitness studio, photography, real
estate, salon & spa, tech startup). Pick one, customise colours and fonts, and start a design
from it. Staff with theme rights create and publish themes.

## Brand kits

**Design → Brand kits** keeps a company's logo, colours, fonts, background, company details
and social links together.
- **New design for this brand** starts a design with the kit applied.
- **Apply to an existing design** swaps in the kit's colours and fonts and, optionally, its background.
- The kit's company details fill merge fields the customer left empty.

## Customers

**Customers → Customers** (staff) lists customers with search and filters. Each customer has
personal and contact details, a company and logo, address and map location, social links,
business description, services, opening hours, custom fields and links, and a visibility
switch per detail for the printed card and for the digital profile.
- Staff set the status, assign a designer and can create a login that emails the customer
  a link to set their password.
- Customers edit their own record under **Account → My card details**.

## Digital profiles

**Customers → Digital profiles**: each profile is a mobile page at `/card/{name}` with call,
WhatsApp, email and website buttons, **Save contact** (downloads a contact card), social
links, services, opening hours, a portfolio gallery, a map, Share and a QR code.
- **Themes**: Classic, Minimal, Bold, Midnight and Elegant, plus accent colour, button shape,
  headline and cover image. Sections can be reordered or switched off.
- **Profile Studio** is a one-page website style for a profile, with its own themes and options.
- Follower counts can be shown for social links: typed in on the profile's Social tab, or
  fetched automatically when the administrator has set up the social API keys.
- **Links**: every profile has a web address, a QR link and an NFC link. Changing the web
  address never breaks printed QR codes or NFC cards.
- **QR downloads**: PNG, SVG or PDF in several sizes, with colour and background options.
  Colours with too little contrast are refused so the code still scans.
- Only details the customer marked "show on profile" appear.

## NFC cards

**NFC & sales → NFC cards** (staff with NFC rights): every physical card has a printed ID
(like `SC-7K2M9QXA`) and a permanent web link that's written onto the chip once.
- Assign a card to a profile, record the chip UID, mark it encoded, move it to another
  profile, replace a lost card or unassign it. Moving a card needs no rewriting.
- Add cards in batches of up to 500. **CSV for encoding** downloads every card's link for an
  NFC writer app or USB encoder. On Android Chrome, **Write with this phone** writes the chip directly.
- Marking a card defective, lost or retired stops it opening the profile straight away.
- The first tap activates a newly assigned card.

## Inventory and orders

- **NFC & sales → Inventory**: products by SKU with price, cost and reorder level, and live
  stock. Low stock is flagged here and on the dashboard.
- **NFC & sales → Orders**: orders per customer with items, tax and currency. Totals are worked
  out automatically. Marking an order paid, processing, shipped or completed counts it as
  revenue. Add the shipped cards by ID and they move from stock to sold.

## Analytics

**NFC & sales → Analytics**: profile views, unique visitors, QR scans, NFC taps, saved contacts
and clicks on each button and link, compared with the previous period, daily, weekly or monthly.
Also devices, countries, how people arrived (QR, NFC, direct) and referring websites.
**Daily CSV** downloads the counts. No IP addresses or cookies are stored. Customers see
analytics for their own profiles.

## Landing pages

**Customers → Landing pages** builds full multi-section marketing websites for a customer's
business (the digital profile stays the one-person card page).
- **Templates**: café & restaurant, real estate, consultant & agency, salon & spa, fitness
  studio, product launch, and blank. The customer's company details are filled in.
- **Sections**: hero, about, services, gallery, numbers, testimonials, pricing, FAQ, call to
  action and contact. Add, reorder, duplicate, hide or delete them, with a live desktop and
  phone preview.
- **Style**: eight palettes, main and accent colours, light / tinted / dark background, five
  font pairings, button shape and scroll animations. **Apply brand kit** uses a kit's colours and details.
- **Publishing**: pages start as drafts and go live at `/p/{address}` when published.
  **Download ZIP** exports a static website for hosts like Netlify Drop or GitHub Pages.
- **Enquiries**: the contact form saves leads on the page's screen and emails the owner.

## Bulk generator

**Customers → Bulk generator** (admins and sales managers) makes many cards at once:
1. **Upload** a CSV or Excel file with one person per row (up to 1000 rows). A template is on the upload page.
2. **Match columns** to fields, pick a theme or design as the template, and choose whether to
   create digital profiles and skip people who already exist.
3. **Check rows**: problems are listed by row and column, with a downloadable report.
4. **Generate**: each valid row becomes a customer, a filled card design and optionally a profile.
5. **Download** every card as a ZIP or one combined PDF, plus a CSV list of names and links.

## Account

- **Account → Settings**: your name, email and password, and API tokens.
- **AI assistant**: this chat. It answers questions about the app and writes copy. It can't
  see or change your data. Conversations are saved under **AI assistant** in the menu and
  only you can see them. There's a daily message limit.

## Writing tips the assistant follows

- Card text: name, job title, one-line tagline (under 40 characters), phone, email, website.
- Profile bio: 2–3 sentences, first person, what you do, who for, and a reason to get in touch.
- Landing page hero: a headline under 8 words, a one-sentence subheadline, one clear button label.
- Services: a short name and one benefit-led sentence each.
- FAQ: real questions customers ask, answers in 1–3 sentences.
