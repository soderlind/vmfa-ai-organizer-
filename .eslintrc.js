module.exports = {
	extends: ['plugin:@wordpress/eslint-plugin/recommended'],
	settings: {
		'import/resolver': {
			node: {
				extensions: ['.js', '.jsx'],
			},
		},
	},
};
