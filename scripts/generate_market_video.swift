// Reproducible, illustrative market animation. No real quotes or market data.
import Foundation
import AVFoundation
import AppKit
let output = CommandLine.arguments.count > 1 ? CommandLine.arguments[1] : "assets/market-loop.mp4"
let width = 1280, height = 720, frames = 240, fps: Int32 = 24
let url = URL(fileURLWithPath: output)
try? FileManager.default.removeItem(at: url)
let writer = try AVAssetWriter(outputURL: url, fileType: .mp4)
let input = AVAssetWriterInput(mediaType: .video, outputSettings: [AVVideoCodecKey: AVVideoCodecType.h264, AVVideoWidthKey: width, AVVideoHeightKey: height, AVVideoCompressionPropertiesKey: [AVVideoAverageBitRateKey: 1_600_000]])
let adaptor = AVAssetWriterInputPixelBufferAdaptor(assetWriterInput: input, sourcePixelBufferAttributes: [kCVPixelBufferPixelFormatTypeKey as String: kCVPixelFormatType_32ARGB, kCVPixelBufferWidthKey as String: width, kCVPixelBufferHeightKey as String: height, kCVPixelBufferCGImageCompatibilityKey as String: true, kCVPixelBufferCGBitmapContextCompatibilityKey as String: true])
writer.add(input); writer.startWriting(); writer.startSession(atSourceTime: .zero)
func color(_ r: CGFloat, _ g: CGFloat, _ b: CGFloat, _ a: CGFloat = 1) -> CGColor { CGColor(red:r,green:g,blue:b,alpha:a) }
for frame in 0..<frames {
    while !input.isReadyForMoreMediaData { if writer.status == .failed { fatalError("Encoder unavailable: \(String(describing:writer.error))") }; Thread.sleep(forTimeInterval: 0.005) }
    var pixel: CVPixelBuffer?
    CVPixelBufferPoolCreatePixelBuffer(nil, adaptor.pixelBufferPool!, &pixel)
    let buffer = pixel!
    CVPixelBufferLockBaseAddress(buffer, [])
    let ctx = CGContext(data: CVPixelBufferGetBaseAddress(buffer), width:width,height:height,bitsPerComponent:8,bytesPerRow:CVPixelBufferGetBytesPerRow(buffer),space:CGColorSpaceCreateDeviceRGB(),bitmapInfo:CGImageAlphaInfo.noneSkipFirst.rawValue)!
    ctx.setFillColor(color(0.106,0.145,0.118)); ctx.fill(CGRect(x:0,y:0,width:width,height:height))
    let phase = Double(frame) / Double(frames) * 2 * Double.pi
    ctx.setStrokeColor(color(0.40,0.51,0.45,0.2)); ctx.setLineWidth(1)
    for x in stride(from:0,through:width,by:80) { ctx.move(to:CGPoint(x:x,y:0));ctx.addLine(to:CGPoint(x:x,y:height)) }
    for y in stride(from:0,through:height,by:60) { ctx.move(to:CGPoint(x:0,y:y));ctx.addLine(to:CGPoint(x:width,y:y)) }
    ctx.strokePath()
    for row in 0..<5 {
        for col in 0..<8 {
            let value = 1000 + Double(row * 627 + col * 123) + 12 * sin(phase + Double(col+row))
            let text = String(format: "%.2f  %+.2f", value, 0.8 * sin(phase + Double(col)))
            let attrs: [NSAttributedString.Key:Any] = [.font:NSFont.monospacedDigitSystemFont(ofSize:16,weight:.regular),.foregroundColor:NSColor(calibratedRed:0.65,green:0.75,blue:0.65,alpha:0.52)]
            NSGraphicsContext.saveGraphicsState(); NSGraphicsContext.current = NSGraphicsContext(cgContext:ctx,flipped:false)
            (text as NSString).draw(at:NSPoint(x:col*175-30,y:row*147+28),withAttributes:attrs)
            NSGraphicsContext.restoreGraphicsState()
        }
    }
    for series in 0..<3 {
        ctx.setStrokeColor(series == 0 ? color(0.92,0.95,0.31,0.85) : color(0.45,0.62,0.51,0.55)); ctx.setLineWidth(series == 0 ? 2.5 : 1.5)
        for x in stride(from:0,through:width,by:8) {
            let t = Double(x)/Double(width)
            let y = 160 + t*260 + 52*sin(t*15 + phase + Double(series)*2) + 22*sin(t*43 - phase) + Double(series)*65
            if x==0 {ctx.move(to:CGPoint(x:Double(x),y:y))} else {ctx.addLine(to:CGPoint(x:Double(x),y:y))}
        }
        ctx.strokePath()
    }
    if frame == 0, let image = ctx.makeImage() {
        let bitmap = NSBitmapImageRep(cgImage:image)
        try bitmap.representation(using:.jpeg,properties:[.compressionFactor:0.85])!.write(to:URL(fileURLWithPath:"assets/market-poster.jpg"))
    }
    CVPixelBufferUnlockBaseAddress(buffer, [])
    if !adaptor.append(buffer, withPresentationTime:CMTime(value:Int64(frame),timescale:fps)) { fatalError("Frame failed: \(String(describing:writer.error))") }
}
input.markAsFinished()
let done = DispatchSemaphore(value:0)
writer.finishWriting { done.signal() }; done.wait()
if writer.status != .completed { fatalError("Video failed: \(String(describing:writer.error))") }
print("Created \(output)")
