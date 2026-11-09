-- events_status_end_index
-- Ne plus modifier une fois mergee : ecrire une nouvelle migration.

-- Feed public (status = 'published' AND end_at > ?) et son COUNT : sans cet index, tous les events publies passes sont parcourus.
CREATE INDEX idx_events_status_end ON events (status, end_at);
