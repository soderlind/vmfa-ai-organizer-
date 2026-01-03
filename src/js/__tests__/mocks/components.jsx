/**
 * Mock for @wordpress/components.
 *
 * @package
 */

export const Button = ({
	children,
	onClick,
	variant,
	isDestructive,
	disabled,
	...props
}) => (
	<button onClick={onClick} disabled={disabled} {...props}>
		{children}
	</button>
);

export const Card = ({ children, ...props }) => (
	<div {...props}>{children}</div>
);
export const CardBody = ({ children, ...props }) => (
	<div {...props}>{children}</div>
);
export const CardHeader = ({ children, ...props }) => (
	<div {...props}>{children}</div>
);

export const Spinner = () => <span>Loading...</span>;

export const Panel = ({ children, header, ...props }) => (
	<div {...props}>
		{header && <div>{header}</div>}
		{children}
	</div>
);

export const PanelBody = ({ children, title, initialOpen, ...props }) => (
	<div {...props}>
		{title && <div>{title}</div>}
		{children}
	</div>
);

export const PanelRow = ({ children, ...props }) => (
	<div {...props}>{children}</div>
);

export const TextControl = ({ label, value, onChange, help, id, ...props }) => {
	const inputId = id || props.name || 'mock-text-control';
	return (
		<div {...props}>
			{label && <label htmlFor={inputId}>{label}</label>}
			<input
				id={inputId}
				type="text"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			/>
			{help && <p>{help}</p>}
		</div>
	);
};

export const SelectControl = ({
	label,
	value,
	options,
	onChange,
	help,
	id,
	...props
}) => {
	const selectId = id || props.name || 'mock-select-control';
	return (
		<div {...props}>
			{label && <label htmlFor={selectId}>{label}</label>}
			<select
				id={selectId}
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			>
				{options.map((opt) => (
					<option key={opt.value} value={opt.value}>
						{opt.label}
					</option>
				))}
			</select>
			{help && <p>{help}</p>}
		</div>
	);
};

export const ToggleControl = ({
	label,
	checked,
	onChange,
	help,
	id,
	...props
}) => {
	const inputId = id || props.name || 'mock-toggle-control';
	return (
		<div {...props}>
			<input
				id={inputId}
				type="checkbox"
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
				aria-label={label}
			/>
			{label && <label htmlFor={inputId}>{label}</label>}
			{help && <p>{help}</p>}
		</div>
	);
};

export const Notice = ({
	children,
	status,
	isDismissible,
	onRemove,
	...props
}) => (
	<div role="alert" {...props}>
		{children}
		{isDismissible && <button onClick={onRemove}>Dismiss</button>}
	</div>
);

export const Modal = ({ children, title, onRequestClose, ...props }) => (
	<div role="dialog" {...props}>
		<div>{title}</div>
		<button onClick={onRequestClose}>Close</button>
		{children}
	</div>
);

export const CheckboxControl = ({ label, checked, onChange, id, ...props }) => {
	const inputId = id || props.name || 'mock-checkbox-control';
	return (
		<div {...props}>
			<input
				id={inputId}
				type="checkbox"
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
				aria-label={label}
			/>
			{label && <label htmlFor={inputId}>{label}</label>}
		</div>
	);
};

export const Flex = ({ children, ...props }) => (
	<div {...props}>{children}</div>
);
export const FlexItem = ({ children, ...props }) => (
	<div {...props}>{children}</div>
);
export const FlexBlock = ({ children, ...props }) => (
	<div {...props}>{children}</div>
);

export const __experimentalText = ({ children, ...props }) => (
	<span {...props}>{children}</span>
);
export const __experimentalHeading = ({ children, level, ...props }) => {
	const Tag = `h${level || 2}`;
	return <Tag {...props}>{children}</Tag>;
};

export const Icon = ({ icon, ...props }) => <span {...props}>{icon}</span>;

export default {
	Button,
	Card,
	CardBody,
	CardHeader,
	Spinner,
	Panel,
	PanelBody,
	PanelRow,
	TextControl,
	SelectControl,
	ToggleControl,
	Notice,
	Modal,
	CheckboxControl,
	Flex,
	FlexItem,
	FlexBlock,
	__experimentalText,
	__experimentalHeading,
	Icon,
};
