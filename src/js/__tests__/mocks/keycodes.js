/**
 * Mock for @wordpress/keycodes.
 *
 * @package VmfaAiOrganizer
 */

export const ENTER = 13;
export const SPACE = 32;
export const BACKSPACE = 8;
export const DELETE = 46;
export const F10 = 121;
export const ALT = 18;
export const CTRL = 17;
export const COMMAND = 91;
export const SHIFT = 16;
export const TAB = 9;
export const DOWN = 40;
export const UP = 38;
export const LEFT = 37;
export const RIGHT = 39;
export const ESCAPE = 27;
export const HOME = 36;
export const END = 35;
export const PAGEUP = 33;
export const PAGEDOWN = 34;
export const ZERO = 48;

export const OS = 'mac';

const createShortcutHelper = () => ( {
	primary: () => '',
	primaryShift: () => '',
	primaryAlt: () => '',
	secondary: () => '',
	access: () => '',
	ctrl: () => '',
	alt: () => '',
	ctrlShift: () => '',
	shift: () => '',
	shiftAlt: () => '',
} );

export const displayShortcut = createShortcutHelper();
export const shortcutAriaLabel = createShortcutHelper();
export const rawShortcut = createShortcutHelper();

export const modifiers = {
	primary: () => [],
	primaryShift: () => [],
	primaryAlt: () => [],
	secondary: () => [],
	access: () => [],
	ctrl: () => [],
	alt: () => [],
	ctrlShift: () => [],
	shift: () => [],
	shiftAlt: () => [],
};

export const isKeyboardEvent = {
	primary: () => false,
	primaryShift: () => false,
	primaryAlt: () => false,
	secondary: () => false,
	access: () => false,
};

export default {
	ENTER,
	SPACE,
	BACKSPACE,
	DELETE,
	F10,
	ALT,
	CTRL,
	COMMAND,
	SHIFT,
	TAB,
	DOWN,
	UP,
	LEFT,
	RIGHT,
	ESCAPE,
	HOME,
	END,
	PAGEUP,
	PAGEDOWN,
	ZERO,
	OS,
	displayShortcut,
	shortcutAriaLabel,
	rawShortcut,
	modifiers,
	isKeyboardEvent,
};
