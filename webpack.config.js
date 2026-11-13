const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { globSync } = require( 'glob' );

const toEntries = ( pattern, suffix = '' ) =>
    Object.fromEntries(
        globSync( pattern, { posix: true } )
            .sort()
            .map( ( file ) => [ path.parse( file ).name + suffix, './' + file ] )
    );

const StaticConfig = {
    ...defaultConfig,
    entry: {
        ...toEntries( 'assets/css/*.scss', '-styles' ),
        ...toEntries( 'assets/js/static/*.js' ),
    },
    resolve: {
        extensions: [ '.js', '.css', '.scss' ],
    },
    output: {
        path: path.resolve( __dirname, './build/static/' ),
        filename: '[name].js',
        devtoolNamespace: 'owbWC',
        library: [ 'owbWC', 'static', '[name]' ],
        libraryTarget: 'window',
    },
};

module.exports = [ StaticConfig ];
