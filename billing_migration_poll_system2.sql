USE poll_system2;

-- Billing columns for paid poll bundles
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS paid_polls_balance INT NOT NULL DEFAULT 0;

ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS plan_polls INT NOT NULL DEFAULT 0;

-- Back-compat for users already marked as paid in the old flow:
-- grant them an initial bundle of 20 paid polls so they can continue.
UPDATE users
SET paid_polls_balance = 20
WHERE is_paid = 1 AND paid_polls_balance = 0;

