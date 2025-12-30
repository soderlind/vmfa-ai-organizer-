/**
 * Mock for @wordpress/components.
 *
 * @package VmfaAiOrganizer
 */

import React from 'react';

export const Button = ( { children, onClick, variant, isDestructive, disabled, ...props } ) => (
	<button onClick={ onClick } disabled={ disabled } { ...props }>
		{ children }
	</button>
);

export const Card = ( { children, ...props } ) => <div { ...props }>{ children }</div>;
export const CardBody = ( { children, ...props } ) => <div { ...props }>{ children }</div>;
export const CardHeader = ( { children, ...props } ) => <div { ...props }>{ children }</div>;

export const Spinner = () => <span>Loading...</span>;

export const Panel = ( { children, header, ...props } ) => (
	<div { ...props }>
		{ header && <div>{ header }</div> }
		{ children }
	</div>
);

export const PanelBody = ( { children, title, initialOpen, ...props } ) => (
	<div { ...props }>
		{ title && <div>{ title }</div> }
		{ children }
	</div>
);

export const PanelRow = ( { children, ...props } ) => <div { ...props }>{ children }</div>;

export const TextControl = ( { label, value, onChange, help, ...props } ) => (
	<div { ...props }>
		{ label && <label>{ label }</label> }
		<input type="text" value={ value } onChange={ ( e ) => onChange( e.target.value ) } />
		{ help && <p>{ help }</p> }
	</div>
);

export const SelectControl = ( { label, value, options, onChange, help, ...props } ) => (
	<div { ...props }>
		{ label && <label>{ label }</label> }
		<select value={ value } onChange={ ( e ) => onChange( e.target.value ) }>
			{ options.map( ( opt ) => (
				<option key={ opt.value } value={ opt.value }>
					{ opt.label }
				</option>
			) ) }
		</select>
		{ help && <p>{ help }</p> }
	</div>
);

export const ToggleControl = ( { label, checked, onChange, help, ...props } ) => (
	<div { ...props }>
		<label>
			<input type="checkbox" checked={ checked } onChange={ ( e ) => onChange( e.target.checked ) } />
			{ label }
		</label>
		{ help && <p>{ help }</p> }
	</div>
);

export const Notice = ( { children, status, isDismissible, onRemove, ...props } ) => (
	<div role="alert" { ...props }>
		{ children }
		{ isDismissible && <button onClick={ onRemove }>Dismiss</button> }
	</div>
);

export const Modal = ( { children, title, onRequestClose, ...props } ) => (
	<div role="dialog" { ...props }>
		<div>{ title }</div>
		<button onClick={ onRequestClose }>Close</button>
		{ children }
	</div>
);

export const CheckboxControl = ( { label, checked, onChange, ...props } ) => (
	<label { ...props }>
		<input type="checkbox" checked={ checked } onChange={ ( e ) => onChange( e.target.checked ) } />
		{ label }
	</label>
);

export const Flex = ( { children, ...props } ) => <div { ...props }>{ children }</div>;
export const FlexItem = ( { children, ...props } ) => <div { ...props }>{ children }</div>;
export const FlexBlock = ( { children, ...props } ) => <div { ...props }>{ children }</div>;

export const __experimentalText = ( { children, ...props } ) => <span { ...props }>{ children }</span>;
export const __experimentalHeading = ( { children, level, ...props } ) => {
	const Tag = `h${ level || 2 }`;
	return <Tag { ...props }>{ children }</Tag>;
};

export const Icon = ( { icon, ...props } ) => <span { ...props }>{ icon }</span>;

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
