export interface ConversionInput {
  fileName: string
  content: string
}

export interface ConversionResult {
  outputFileName: string
  content: string
  warnings: string[]
}

export interface ConverterService {
  convert(input: ConversionInput): Promise<ConversionResult>
}
