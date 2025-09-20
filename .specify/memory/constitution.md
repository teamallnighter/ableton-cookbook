<!--
Sync Impact Report - Constitution Update
========================================
Version change: 1.0.0 → 1.1.0 (Analysis Completeness Principle added)
Modified principles: Added Analysis Completeness Principle to business rules
Added sections:
- Analysis Completeness Principle (Business Rules)
- Nested chain detection requirement
Changes from previous version:
- MINOR: New business rule for complete chain analysis
- Added requirement that ALL CHAINS must be detected regardless of nesting depth
Templates requiring updates: ✅ Plan template already updated for constitutional checks
Follow-up TODOs: None - constitutional amendment complete
-->

# Ableton Cookbook Constitution

## Core Principles

### I. API-First Design
Every feature MUST be accessible via REST API before UI implementation. Complete OpenAPI documentation is required. Both session and token authentication must be supported. API rate limiting shall be implemented with specialized limits (60/min analysis, 10/min batch operations). Dual documentation systems (Swagger + Scramble) ensure comprehensive developer resources.

**Rationale**: Enables third-party integrations, mobile apps, and automated workflows while maintaining consistency between UI and programmatic access.

### II. Service Architecture Excellence
Business logic MUST reside in dedicated service classes (40+ specialized services). Controllers shall remain thin, delegating to services. Service-oriented architecture with clear separation of concerns is mandatory. Environment-aware configuration allows seamless development/production transitions.

**Rationale**: Promotes testability, reusability, and maintainability while enabling independent testing and deployment of business logic.

### III. Security-By-Design
Authentication is required for rack uploads and modifications. XSS protection through multi-layer sanitization is mandatory. CSRF protection must be active. File uploads require strict validation (.adg format, size limits). Rate limiting protects against abuse. GDPR compliance with explicit consent mechanisms.

**Rationale**: Protects user data and platform integrity while meeting legal compliance requirements in a community-driven platform.

### IV. Testing Discipline
Feature tests for API endpoints and user workflows are required. Unit tests for service classes are mandatory. Test coverage must expand toward 90%. Integration tests required for service communication and contract changes. Laravel's testing framework shall be the standard.

**Rationale**: Ensures reliability and prevents regressions in a platform handling community-contributed content and complex file processing.

### V. Emergency Response Capability
Production issues must be diagnosed within 15 minutes using Laravel logs. Git-based fixes are preferred over direct server modifications. All emergency responses require documentation. Health monitoring systems must be maintained. Systematic approach: Logs → Root cause → Local fix → Git deployment.

**Rationale**: Minimizes downtime and maintains service quality expectations for a community platform with production dependencies.

## Security & Compliance Requirements

Data protection measures include UUID-based file naming, private disk storage, and secure authentication systems. Content sanitization prevents XSS attacks in user-generated content. File processing must validate formats before decompression. Email systems require SPF/DKIM/DMARC configuration. Audit trails for administrative actions are mandatory.

## Development Quality Standards

Laravel conventions must be followed including controller/service/model organization. Code quality metrics include function refactoring tracking, linting compliance, and performance optimization documentation. Git repository must maintain clean history with descriptive commit messages. Documentation updates are required for architectural changes.

Automated deployment supports Ubuntu environments with health checks. Cache management strategies (Redis) optimize performance. Error tracking and logging facilitate debugging and monitoring.

## Governance

This constitution supersedes all other development practices. Amendments require documentation, approval, and migration planning. All code reviews must verify constitutional compliance. Complexity implementations require explicit justification aligned with these principles.

Constitutional violations halt development until resolved. Regular compliance reviews ensure adherence. Emergency exceptions require post-resolution constitutional analysis and documentation.

Runtime development guidance resides in CLAUDE.md. Project-specific constraints include:
- Max how-to article size: 100KB (100,000 characters)
- Max tags per rack: 10 tags, each max 50 characters
- Rack title limit: 255 characters
- Description limit: 1,000 characters
- Auto-save rate limit: 30 requests/minute

## Analysis Completeness Principle

**Business Rule**: ALL CHAINS within uploaded rack files MUST be detected and included in analysis, regardless of nesting depth. This is a constitutional requirement that cannot be violated.

**Implementation Requirements**:
- Rack analysis cannot be marked complete unless all nested chains are detected
- Nested chain detection must handle arbitrary depth (up to reasonable limit of 10)
- Analysis must preserve full hierarchy of parent-child relationships
- Constitutional compliance reports must verify complete chain detection
- Any rack missing nested chain data is considered non-compliant

**Rationale**: Incomplete chain analysis compromises the educational value and discovery capabilities of the platform. Users must have full visibility into complex rack structures to understand and learn from community contributions.

**Version**: 1.1.0 | **Ratified**: 2025-09-20 | **Last Amended**: 2025-09-20