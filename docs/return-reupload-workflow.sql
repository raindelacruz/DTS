-- Return-and-reupload workflow for DTS document attachments.
-- Run this against the DTS database before deploying the matching code changes.

ALTER TABLE documents
  MODIFY status ENUM('Draft','Released','Received','Returned','Re-released') DEFAULT 'Draft';

ALTER TABLE document_routes
  MODIFY status ENUM('Pending','Received','Returned') DEFAULT 'Pending';

CREATE TABLE IF NOT EXISTS document_returns (
  id INT(11) NOT NULL AUTO_INCREMENT,
  document_id INT(11) NOT NULL,
  route_id INT(11) DEFAULT NULL,
  returned_by INT(11) NOT NULL,
  returned_department_id INT(11) NOT NULL,
  releasing_department_id INT(11) NOT NULL,
  return_reason VARCHAR(150) NOT NULL,
  attachment_issue VARCHAR(80) DEFAULT NULL,
  remarks TEXT NOT NULL,
  status ENUM('Open','Resolved') NOT NULL DEFAULT 'Open',
  returned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME DEFAULT NULL,
  resolved_by INT(11) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_document_returns_document (document_id),
  KEY idx_document_returns_status (status),
  KEY idx_document_returns_route (route_id),
  CONSTRAINT document_returns_document_fk FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT document_returns_route_fk FOREIGN KEY (route_id) REFERENCES document_routes (id) ON DELETE SET NULL,
  CONSTRAINT document_returns_returned_by_fk FOREIGN KEY (returned_by) REFERENCES users (id),
  CONSTRAINT document_returns_returned_department_fk FOREIGN KEY (returned_department_id) REFERENCES departments (id),
  CONSTRAINT document_returns_releasing_department_fk FOREIGN KEY (releasing_department_id) REFERENCES departments (id),
  CONSTRAINT document_returns_resolved_by_fk FOREIGN KEY (resolved_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS document_attachment_history (
  id INT(11) NOT NULL AUTO_INCREMENT,
  document_id INT(11) NOT NULL,
  return_id INT(11) DEFAULT NULL,
  old_filename VARCHAR(255) DEFAULT NULL,
  new_filename VARCHAR(255) NOT NULL,
  uploaded_by INT(11) NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  replacement_reason TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_document_attachment_history_document (document_id),
  KEY idx_document_attachment_history_return (return_id),
  KEY idx_document_attachment_history_active (document_id, is_active),
  CONSTRAINT document_attachment_history_document_fk FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
  CONSTRAINT document_attachment_history_return_fk FOREIGN KEY (return_id) REFERENCES document_returns (id) ON DELETE SET NULL,
  CONSTRAINT document_attachment_history_uploaded_by_fk FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
