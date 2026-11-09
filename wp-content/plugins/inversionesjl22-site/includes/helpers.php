<?php
/**
 * Helpers shared by the order, report and QR features.
 *
 * Moved from WPCodeBox › Utils (snippets 59 "Format Date Range" and 60 "Get Pick Up Shipping
 * Methods"). The function names stay as they were: the snippets that are still in WPCodeBox
 * call them by these names. Each one is declared only if it is not there yet, so turning off
 * one of the two snippets before the other does not declare it twice.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'formatDateRange' ) ) {
	/**
	 * A date range in Spanish, for the shipping report header.
	 *
	 *   same month:  "Del 23 al 24 de Enero"
	 *   same year:   "Del 23 de Enero al 3 de Febrero"
	 *   two years:   "Del 30 de Diciembre del 2026 al 3 de Enero del 2027"
	 *
	 * @param string $start Any date DateTime understands (Y-m-d from the report form).
	 * @param string $end   Same.
	 */
	function formatDateRange( $start, $end ) {
		$start_date = new DateTime( $start );
		$end_date   = new DateTime( $end );

		$months = array(
			1  => 'Enero',
			2  => 'Febrero',
			3  => 'Marzo',
			4  => 'Abril',
			5  => 'Mayo',
			6  => 'Junio',
			7  => 'Julio',
			8  => 'Agosto',
			9  => 'Septiembre',
			10 => 'Octubre',
			11 => 'Noviembre',
			12 => 'Diciembre',
		);

		$start_day   = $start_date->format( 'j' );
		$start_month = (int) $start_date->format( 'n' );
		$start_year  = $start_date->format( 'Y' );

		$end_day   = $end_date->format( 'j' );
		$end_month = (int) $end_date->format( 'n' );
		$end_year  = $end_date->format( 'Y' );

		if ( $start_month === $end_month && $start_year === $end_year ) {
			return "Del $start_day al $end_day de {$months[ $start_month ]}";
		}

		if ( $start_year === $end_year ) {
			return "Del $start_day de {$months[ $start_month ]} al $end_day de {$months[ $end_month ]}";
		}

		return "Del $start_day de {$months[ $start_month ]} del $start_year al $end_day de {$months[ $end_month ]} del $end_year";
	}
}

if ( ! function_exists( 'getPickUpShippingMethods' ) ) {
	/**
	 * Titles of the shipping methods (as named in WooCommerce › Settings › Shipping) whose orders
	 * are paid on delivery: they show the "payment status" box in the order and need it set
	 * (Pagado / Por cobrar) before their status can change.
	 *
	 * @return string[]
	 */
	function getPickUpShippingMethods() {
		return array(
			'CONTRA ENTREGA EFECTIVO',
			'DOMICILIO CUCUTA',
			'DOMICILIO SAN CRISTOBAL',
			'ENTREGAS TEALCA',
		);
	}
}
