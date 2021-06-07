<?php

namespace FondOfOryx\Zed\SplittableQuote\Business\Splitter;

use ArrayObject;
use Codeception\Test\Unit;
use FondOfOryx\Zed\SplittableQuote\Dependency\Facade\SplittableQuoteToCalculationFacadeInterface;
use FondOfOryx\Zed\SplittableQuote\SplittableQuoteConfig;
use FondOfOryx\Zed\SplittableQuoteExtension\Dependency\Plugin\SplittedQuoteExpanderPluginInterface;
use Generated\Shared\Transfer\ItemTransfer;
use Generated\Shared\Transfer\QuoteTransfer;

class QuoteSplitterTest extends Unit
{
    /**
     * @var \FondOfOryx\Zed\SplittableQuote\Dependency\Facade\SplittableQuoteToCalculationFacadeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $calculationFacadeMock;

    /**
     * @var \FondOfOryx\Zed\SplittableQuote\SplittableQuoteConfig|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $configMock;

    /**
     * @var \FondOfOryx\Zed\SplittableQuoteExtension\Dependency\Plugin\SplittedQuoteExpanderPluginInterface[]|\PHPUnit\Framework\MockObject\MockObject[]
     */
    protected $splittedQuoteExpanderPluginMocks;

    /**
     * @var \Generated\Shared\Transfer\QuoteTransfer|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $quoteTransferMock;

    /**
     * @var \Generated\Shared\Transfer\ItemTransfer[]|\PHPUnit\Framework\MockObject\MockObject[]
     */
    protected $itemTransferMocks;

    /**
     * @var \FondOfOryx\Zed\SplittableQuote\Business\Splitter\QuoteSplitter
     */
    protected $quoteSplitter;

    /**
     * @return void
     */
    protected function _before(): void
    {
        parent::_before();

        $this->calculationFacadeMock = $this->getMockBuilder(SplittableQuoteToCalculationFacadeInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configMock = $this->getMockBuilder(SplittableQuoteConfig::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->splittedQuoteExpanderPluginMocks = [
            $this->getMockBuilder(SplittedQuoteExpanderPluginInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
        ];

        $this->quoteTransferMock = $this->getMockBuilder(QuoteTransfer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->itemTransferMocks = [
            $this->getMockBuilder(ItemTransfer::class)
                ->disableOriginalConstructor()
                ->getMock(),
        ];

        $this->quoteSplitter = new QuoteSplitter(
            $this->calculationFacadeMock,
            $this->configMock,
            $this->splittedQuoteExpanderPluginMocks
        );
    }

    /**
     * @return void
     */
    public function testSplit(): void
    {
        $groupKey = 'foo';

        $this->configMock->expects(static::atLeastOnce())
            ->method('getSplitItemAttribute')
            ->willReturn('group_key');

        $this->quoteTransferMock->expects(static::atLeastOnce())
            ->method('getItems')
            ->willReturn(new ArrayObject($this->itemTransferMocks));

        $this->itemTransferMocks[0]->expects(static::atLeastOnce())
            ->method('getGroupKey')
            ->willReturn($groupKey);

        $this->quoteTransferMock->expects(static::atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $this->splittedQuoteExpanderPluginMocks[0]->expects(static::atLeastOnce())
            ->method('expand')
            ->with(
                static::callback(
                    static function (QuoteTransfer $quoteTransfer) {
                        return $quoteTransfer->getIdQuote() === null
                            && $quoteTransfer->getUuid() === null
                            && !$quoteTransfer->getIsDefault();
                    }
                )
            )
            ->willReturn($this->quoteTransferMock);

        $this->calculationFacadeMock->expects(static::atLeastOnce())
            ->method('recalculateQuote')
            ->with(
                static::callback(
                    static function (QuoteTransfer $quoteTransfer) {
                        return $quoteTransfer->getIdQuote() === null
                            && $quoteTransfer->getUuid() === null
                            && !$quoteTransfer->getIsDefault();
                    }
                ),
                false
            )
            ->willReturn($this->quoteTransferMock);

        $quoteTransfers = $this->quoteSplitter->split($this->quoteTransferMock);

        static::assertCount(1, $quoteTransfers);
        static::assertArrayHasKey($groupKey, $quoteTransfers);
    }

    /**
     * @return void
     */
    public function testSplitWithoutSplitItemAttribute(): void
    {
        $this->configMock->expects(static::atLeastOnce())
            ->method('getSplitItemAttribute')
            ->willReturn(null);

        $this->quoteTransferMock->expects(static::never())
            ->method('getItems');

        $this->quoteTransferMock->expects(static::never())
            ->method('toArray');

        $this->splittedQuoteExpanderPluginMocks[0]->expects(static::never())
            ->method('expand');

        $this->calculationFacadeMock->expects(static::never())
            ->method('recalculateQuote');

        $quoteTransfers = $this->quoteSplitter->split($this->quoteTransferMock);

        static::assertCount(1, $quoteTransfers);
        static::assertArrayHasKey('*', $quoteTransfers);
        static::assertEquals($this->quoteTransferMock, $quoteTransfers['*']);
    }

    /**
     * @return void
     */
    public function testSplitWithUndefinedSplitItemAttribute(): void
    {
        $this->configMock->expects(static::atLeastOnce())
            ->method('getSplitItemAttribute')
            ->willReturn('xxx_yyy_zzz');

        $this->quoteTransferMock->expects(static::never())
            ->method('getItems');

        $this->quoteTransferMock->expects(static::never())
            ->method('toArray');

        $this->splittedQuoteExpanderPluginMocks[0]->expects(static::never())
            ->method('expand');

        $this->calculationFacadeMock->expects(static::never())
            ->method('recalculateQuote');

        $quoteTransfers = $this->quoteSplitter->split($this->quoteTransferMock);

        static::assertCount(1, $quoteTransfers);
        static::assertArrayHasKey('*', $quoteTransfers);
        static::assertEquals($this->quoteTransferMock, $quoteTransfers['*']);
    }
}
