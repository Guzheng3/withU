## ADDED Requirements

### Requirement: Select dropdown background color
All `<select>` elements using the `input-field` class SHALL display with a dark semi-transparent background (`rgba(255, 255, 255, 0.05)`) matching the dark theme.

#### Scenario: Log type dropdown background
- **WHEN** user views the log page with dark theme enabled
- **THEN** the log type dropdown SHALL display with `bg-white/5` dark background
- **AND** the dropdown arrow SHALL use white stroke color

#### Scenario: Log level dropdown background
- **WHEN** user views the log page and selects a log level
- **THEN** the log level dropdown SHALL display with consistent dark background styling
- **AND** the dropdown options SHALL be readable with white text on dark background

### Requirement: Select dropdown text color
All `<select>` elements using the `input-field` class SHALL display text in white color to ensure readability on dark backgrounds.

#### Scenario: Dropdown options text color
- **WHEN** user opens any dropdown select
- **THEN** all option text SHALL appear in white color (`text-white`)
- **AND** selected option SHALL maintain white text color

### Requirement: Custom dropdown arrow icon
All `<select>` elements SHALL display a custom white arrow icon to indicate dropdown functionality.

#### Scenario: Dropdown arrow visibility
- **WHEN** user sees any `<select>` element with `input-field` class
- **THEN** a white SVG arrow icon SHALL appear on the right side
- **AND** the arrow SHALL align vertically centered

#### Scenario: Dropdown arrow position
- **WHEN** dropdown arrow is displayed
- **THEN** it SHALL be positioned 0.75rem from the right edge
- **AND** it SHALL have a size of 1.5em × 1.5em
- **AND** it SHALL be vertically centered within the select element

### Requirement: Cross-browser select styling consistency
All `<select>` elements SHALL have consistent appearance across different browsers by overriding default browser styles.

#### Scenario: Firefox dropdown styling
- **WHEN** user views dropdown on Firefox browser
- **THEN** the dropdown SHALL display dark background and white text
- **AND** the native Firefox dropdown style SHALL be overridden

#### Scenario: Chrome dropdown styling
- **WHEN** user views dropdown on Chrome browser
- **THEN** the dropdown SHALL display dark background and white text
- **AND** the native Chrome dropdown style SHALL be overridden

#### Scenario: Safari dropdown styling
- **WHEN** user views dropdown on Safari browser
- **THEN** the dropdown SHALL display dark background and white text
- **AND** the native Safari dropdown style SHALL be overridden
