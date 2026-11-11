<?php

namespace App\Exports;

use App\Enums\MonthEnum;
use App\Exports\BuffaloDerivedProducts\BuffaloDerivedProductsFilters;
use App\Exports\BuffaloDerivedProducts\Strategies\AreaLayoutStrategy;
use App\Exports\BuffaloDerivedProducts\Strategies\DateAnnualLayoutStrategy;
use App\Exports\BuffaloDerivedProducts\Strategies\DateMonthlyLayoutStrategy;
use App\Exports\BuffaloDerivedProducts\Strategies\YearlyLayoutStrategy;
use App\Exports\BuffaloDerivedProducts\Support\BdpLocationResolver;
use App\Exports\BuffaloDerivedProducts\Support\BdpMatrixBuilder;
use App\Exports\BuffaloDerivedProducts\Support\BdpQueryBuilder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BuffaloDerivedProductsExport implements FromArray, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private array $headerMerges = [];

    private int $headerRows = 1;

    private BuffaloDerivedProductsFilters $filters;

    private BdpQueryBuilder $queryBuilder;

    private BdpMatrixBuilder $matrixBuilder;

    private BdpLocationResolver $locationResolver;

    private YearlyLayoutStrategy $yearlyStrategy;

    private DateAnnualLayoutStrategy $dateAnnualStrategy;

    private DateMonthlyLayoutStrategy $dateMonthlyStrategy;

    private AreaLayoutStrategy $areaStrategy;

    public function __construct(
        protected string $template = 'bdp-by-year',
        protected ?string $templateVariant = null,
        protected string $product = 'milk',
        protected string $type = 'production',
        protected int|array|null $year = null,
        protected int|array|null $centerId = null,
        protected int|array|null $month = null,
        protected string|array|null $region = null,
        protected string|array|null $province = null,
        protected string|array|null $district = null,
        protected string|array|null $city = null,
        protected ?string $program = null,
        protected string|array|null $partner = null,
        protected string|array|null $farmer = null,
    ) {
        $this->filters = BuffaloDerivedProductsFilters::fromArguments(
            template: $template,
            templateVariant: $templateVariant,
            product: $product,
            type: $type,
            year: $year,
            centerId: $centerId,
            month: $month,
            region: $region,
            province: $province,
            district: $district,
            city: $city,
            program: $program,
            partner: $partner,
            farmer: $farmer,
        );

        $this->queryBuilder = new BdpQueryBuilder($this->filters);
        $this->matrixBuilder = new BdpMatrixBuilder;
        $this->locationResolver = new BdpLocationResolver($this->filters);
        $this->yearlyStrategy = new YearlyLayoutStrategy($this->queryBuilder, $this->matrixBuilder);
        $this->dateAnnualStrategy = new DateAnnualLayoutStrategy($this->queryBuilder, $this->locationResolver);
        $this->dateMonthlyStrategy = new DateMonthlyLayoutStrategy($this->queryBuilder, $this->matrixBuilder);
        $this->areaStrategy = new AreaLayoutStrategy($this->queryBuilder, $this->locationResolver);
    }

    public function title(): string
    {
        return ucfirst($this->product).' '.ucfirst($this->type);
    }

    public function array(): array
    {
        $source = config("reports.bdp.sources.{$this->product}.{$this->type}");
        if (! $source || ! isset($source['table'], $source['metric'])) {
            return [[$this->metricLabel(), 'No data available']];
        }

        $rows = $this->queryBuilder->fetchRows($source['table'], $source['metric']);
        $matrix = $this->matrixBuilder->build($rows);

        if (empty($matrix['years'])) {
            return [[$this->metricLabel(), 'No data available']];
        }

        if ($this->template === 'date-annual') {
            $rows = $this->dateAnnualStrategy->build($matrix, $this->filters);

            $this->headerMerges = $this->dateAnnualStrategy->headerMerges();
            $this->headerRows = $this->dateAnnualStrategy->headerRows();

            return $rows;
        }

        return match ($this->template) {
            'date-monthly' => $this->dateMonthlyStrategy->build($matrix, $this->filters),
            'area' => $this->areaStrategy->build($matrix, $this->filters),
            default => $this->yearlyStrategy->build($matrix, $this->filters),
        };
    }

    public function metricLabel(): string
    {
        $productLabel = config("reports.bdp.products.{$this->product}", ucfirst($this->product));
        $typeLabel = config("reports.bdp.types.{$this->type}", ucfirst($this->type));

        return strtoupper($productLabel.' '.$typeLabel);
    }

    public function monthLabels(): array
    {
        $labels = MonthEnum::labels();

        return array_map(
            fn (int $month) => strtoupper(substr($labels[$month] ?? '', 0, 3)),
            range(1, 12)
        );
    }

    public function format(float $value): string
    {
        return number_format($value, 2);
    }

    public function cell(float $value): string
    {
        return $value > 0 ? number_format($value, 2) : '';
    }

    private function headerRowCount(): int
    {
        return $this->headerRows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->headerMerges as $range) {
                    $sheet->mergeCells($range);
                }

                $sheet->freezePane('A'.($this->headerRowCount() + 1));
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $totalRows = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();
        $headerRows = $this->headerRowCount();

        $styles = [
            "A1:{$lastColumn}{$totalRows}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];

        for ($i = 1; $i <= $headerRows; $i++) {
            $styles["A{$i}:{$lastColumn}{$i}"] = [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'f0f0f0'],
                ],
            ];
        }

        for ($i = 1; $i <= $totalRows; $i++) {
            $first = $sheet->getCell("A{$i}")->getValue();

            if (in_array(
                strtoupper((string) $first),
                ['YEAR', 'TOTAL', 'GRAND TOTAL', 'CENTER', 'REGION', 'PROVINCE', 'MUNICIPALITY', 'DISTRICT', 'PARTNER', 'PROGRAM', 'TYPE'],
                true
            )) {
                $styles["A{$i}:{$lastColumn}{$i}"] = [
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'f0f0f0'],
                    ],
                ];
            }
        }

        return $styles;
    }
}
