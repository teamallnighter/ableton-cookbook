# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Ableton Cookbook is a web-based platform for Ableton Live users to discover, share, and collaborate on music production workflows. The platform allows users to scan, upload, share, and discover workflows (effect racks, instrument racks, and production chains) from the global producer community.

### Core Features
- **Workflow Scanner**: Automated detection and cataloging of Ableton racks (.adg files)
- **Workflow Sharing Platform**: Community-driven content sharing system  
- **Rating & Review System**: Quality-driven content curation
- **Social Following System**: Connect with inspiring producers

## Technology Stack

This project is implemented in PHP, transitioning from a previous Python implementation. The codebase is currently minimal with only documentation files present.

## Project Status

The repository is in early development phase with only PRD.md and README.md files present. The actual PHP implementation has not yet begun, though the PRD indicates this is a restart of a previous project that encountered challenges.

## Development Setup

Since no PHP code or configuration files exist yet, standard PHP development practices will apply:
- PHP web server setup will be needed
- Database configuration (likely MySQL/PostgreSQL for user data, workflows, ratings)
- File handling for .adg (Ableton rack) file processing
- Audio file processing capabilities for preview generation

## Key Technical Requirements

Based on the PRD, the system needs to handle:
- Ableton .adg file parsing and analysis
- Audio file processing for workflow previews
- User authentication and authorization
- File upload and storage management
- Search and filtering capabilities
- Rating and review systems
- Social features (following, user profiles)

## Target Metrics

- 10,000+ registered users within 6 months
- 5,000+ shared workflows within first year  
- Average user session duration of 15+ minutes
- 70%+ user retention rate after 30 days