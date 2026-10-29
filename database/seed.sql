-- CampusFind sample data (FICTIONAL, for local demonstration only).
-- database/setup.php creates the four demo users first (with password_hash),
-- then runs this file. Rows below find users and categories by email/name,
-- so they never depend on hard-coded ids.

-- ------------------------------------------------------------ categories
INSERT INTO categories (name, description, created_at) VALUES
 ('Wallets and Purses',        'Wallets, coin purses, card holders',                datetime('now','localtime','-30 days')),
 ('Identification Cards',      'School IDs, lanyards, government IDs, ATM cards',   datetime('now','localtime','-30 days')),
 ('Gadgets and Electronics',   'Phones, chargers, earphones, calculators, laptops', datetime('now','localtime','-30 days')),
 ('Keys',                      'House keys, locker keys, motorcycle keys',          datetime('now','localtime','-30 days')),
 ('Bags',                      'Backpacks, tote bags, pouches',                     datetime('now','localtime','-30 days')),
 ('Clothing',                  'Jackets, uniforms, caps, scarves',                  datetime('now','localtime','-30 days')),
 ('Books and School Supplies', 'Books, notebooks, pens, art materials',             datetime('now','localtime','-30 days')),
 ('Other',                     'Anything that does not fit the other categories',   datetime('now','localtime','-30 days'));

-- --------------------------------------------------------- item reports
INSERT INTO item_reports (user_id, category_id, report_type, item_name, description, location, date_occurred, status, created_at, updated_at) VALUES
 ((SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Other'),
  'found', 'Blue insulated tumbler',
  'Navy-blue insulated tumbler with a dented bottom and a white sticker on the side.',
  'Canteen, near the cashier', date('now','localtime','-1 day'), 'open',
  datetime('now','localtime','-1 day'), datetime('now','localtime','-1 day')),

 ((SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Wallets and Purses'),
  'found', 'Black bifold wallet',
  'Black leather bifold wallet left on a reading table. Contents will be checked by staff during claim verification.',
  'Main Library, 2nd floor reading area', date('now','localtime','-2 days'), 'claim_pending',
  datetime('now','localtime','-2 days'), datetime('now','localtime','-1 day')),

 ((SELECT id FROM users WHERE email='ana.reyes@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Identification Cards'),
  'found', 'Green lanyard with ID holder',
  'Green lanyard with a clear ID holder. Please describe the card inside when you claim it.',
  'Engineering Building, canteen entrance', date('now','localtime','-4 days'), 'awaiting_handover',
  datetime('now','localtime','-4 days'), datetime('now','localtime','-2 days')),

 ((SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Keys'),
  'found', 'Set of keys with Doraemon keychain',
  'Three keys on a ring with a blue Doraemon keychain.',
  'Gymnasium entrance', date('now','localtime','-9 days'), 'returned',
  datetime('now','localtime','-9 days'), datetime('now','localtime','-6 days')),

 ((SELECT id FROM users WHERE email='ana.reyes@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Gadgets and Electronics'),
  'lost', 'Casio fx-991EX calculator',
  'Gray scientific calculator with a scratched back cover. It was in a black hard case.',
  'Room 305, IT Building', date('now','localtime','-3 days'), 'open',
  datetime('now','localtime','-3 days'), datetime('now','localtime','-3 days')),

 ((SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Bags'),
  'lost', 'Gray Jansport backpack',
  'Gray backpack with a black zipper pull and a small basketball keychain.',
  'Covered court bleachers', date('now','localtime','-2 days'), 'open',
  datetime('now','localtime','-2 days'), datetime('now','localtime','-2 days')),

 ((SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Clothing'),
  'found', 'Navy blue school jacket',
  'Navy-blue zip-up jacket, size M, with no name tag.',
  'Room 210, Business Administration Building', date('now','localtime','-20 days'), 'closed',
  datetime('now','localtime','-20 days'), datetime('now','localtime','-5 days')),

 ((SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  (SELECT id FROM categories WHERE name='Books and School Supplies'),
  'found', 'Red Statistics notebook',
  'Red spiral notebook labeled Statistics, with handwritten notes in blue ink.',
  'Study area near the Registrar', date('now','localtime','-6 days'), 'open',
  datetime('now','localtime','-6 days'), datetime('now','localtime','-4 days'));

-- ---------------------------------------------------------------- claims
INSERT INTO claim_requests (item_report_id, claimant_id, claim_details, status, reviewed_by, reviewed_at, staff_notes, created_at, updated_at) VALUES
 ((SELECT id FROM item_reports WHERE item_name='Black bifold wallet'),
  (SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  'The wallet has my school ID, a folded 200-peso bill in the coin pocket, and a small photo of my dog behind the ID.',
  'pending', NULL, NULL, NULL,
  datetime('now','localtime','-1 day'), datetime('now','localtime','-1 day')),

 ((SELECT id FROM item_reports WHERE item_name='Green lanyard with ID holder'),
  (SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  'The card inside is my school ID with a photo of me wearing glasses. The lanyard has a small yellow pin near the clip.',
  'approved',
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  datetime('now','localtime','-2 days'),
  'Details match the ID inside the holder. Please bring a valid ID to the Lost & Found Desk to collect the item.',
  datetime('now','localtime','-3 days'), datetime('now','localtime','-2 days')),

 ((SELECT id FROM item_reports WHERE item_name='Set of keys with Doraemon keychain'),
  (SELECT id FROM users WHERE email='ana.reyes@student.campusfind.test'),
  'There are three keys: a silver house key, a small locker key marked 14B, and a black motorcycle key.',
  'approved',
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  datetime('now','localtime','-7 days'),
  'Key count and the locker number match. Approved for pickup.',
  datetime('now','localtime','-8 days'), datetime('now','localtime','-6 days')),

 ((SELECT id FROM item_reports WHERE item_name='Red Statistics notebook'),
  (SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  'It is my notebook for Statistics class.',
  'rejected',
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  datetime('now','localtime','-4 days'),
  'The details were too general to verify ownership. You may submit a new claim with specific details, such as the topic of the last page.',
  datetime('now','localtime','-5 days'), datetime('now','localtime','-4 days'));

-- -------------------------------------------------------- status history
INSERT INTO status_history (item_report_id, changed_by, old_status, new_status, notes, created_at) VALUES
 -- Blue insulated tumbler
 ((SELECT id FROM item_reports WHERE item_name='Blue insulated tumbler'),
  (SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-1 day')),
 -- Black bifold wallet
 ((SELECT id FROM item_reports WHERE item_name='Black bifold wallet'),
  (SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-2 days')),
 ((SELECT id FROM item_reports WHERE item_name='Black bifold wallet'),
  (SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  'open', 'claim_pending', 'A claim was submitted', datetime('now','localtime','-1 day')),
 -- Green lanyard
 ((SELECT id FROM item_reports WHERE item_name='Green lanyard with ID holder'),
  (SELECT id FROM users WHERE email='ana.reyes@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-4 days')),
 ((SELECT id FROM item_reports WHERE item_name='Green lanyard with ID holder'),
  (SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  'open', 'claim_pending', 'A claim was submitted', datetime('now','localtime','-3 days')),
 ((SELECT id FROM item_reports WHERE item_name='Green lanyard with ID holder'),
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  'claim_pending', 'awaiting_handover', 'Claim approved. Waiting for the claimant to collect the item.', datetime('now','localtime','-2 days')),
 -- Keys
 ((SELECT id FROM item_reports WHERE item_name='Set of keys with Doraemon keychain'),
  (SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-9 days')),
 ((SELECT id FROM item_reports WHERE item_name='Set of keys with Doraemon keychain'),
  (SELECT id FROM users WHERE email='ana.reyes@student.campusfind.test'),
  'open', 'claim_pending', 'A claim was submitted', datetime('now','localtime','-8 days')),
 ((SELECT id FROM item_reports WHERE item_name='Set of keys with Doraemon keychain'),
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  'claim_pending', 'awaiting_handover', 'Claim approved. Waiting for the claimant to collect the item.', datetime('now','localtime','-7 days')),
 ((SELECT id FROM item_reports WHERE item_name='Set of keys with Doraemon keychain'),
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  'awaiting_handover', 'returned', 'Handed over to the claimant at the Lost & Found Desk.', datetime('now','localtime','-6 days')),
 -- Calculator and backpack (lost reports)
 ((SELECT id FROM item_reports WHERE item_name='Casio fx-991EX calculator'),
  (SELECT id FROM users WHERE email='ana.reyes@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-3 days')),
 ((SELECT id FROM item_reports WHERE item_name='Gray Jansport backpack'),
  (SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-2 days')),
 -- Navy jacket
 ((SELECT id FROM item_reports WHERE item_name='Navy blue school jacket'),
  (SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-20 days')),
 ((SELECT id FROM item_reports WHERE item_name='Navy blue school jacket'),
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  'open', 'closed', 'Unclaimed after 15 days. Closed by staff.', datetime('now','localtime','-5 days')),
 -- Red notebook
 ((SELECT id FROM item_reports WHERE item_name='Red Statistics notebook'),
  (SELECT id FROM users WHERE email='maria.santos@student.campusfind.test'),
  NULL, 'open', 'Report created', datetime('now','localtime','-6 days')),
 ((SELECT id FROM item_reports WHERE item_name='Red Statistics notebook'),
  (SELECT id FROM users WHERE email='juan.delacruz@student.campusfind.test'),
  'open', 'claim_pending', 'A claim was submitted', datetime('now','localtime','-5 days')),
 ((SELECT id FROM item_reports WHERE item_name='Red Statistics notebook'),
  (SELECT id FROM users WHERE email='admin@campusfind.test'),
  'claim_pending', 'open', 'Claim rejected. The report is open for new claims.', datetime('now','localtime','-4 days'));
